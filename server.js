const express = require('express'),
    http = require('http'),
    app = express(),
    server = http.createServer(app),
    io = require('socket.io').listen(server),
    Redis = require('ioredis');
// var http = require('http').Server(app)
var redis = new Redis() //subscribe channel
var pub = new Redis() //publish channel
var cache = new Redis() //cache channel

var driver_room = new Array();
var transaction_room = new Array();
var passengerMessage = new Array();
var qrcode_room = new Array();

//Call when there is someone connected to the server
io.sockets.on('connection', (socket) => {
    console.log('new user connected')

    //Listen for user(passenger/driver) joining the server
    socket.on('join', (user) => {
        switch (user.identity) {
            case 'driver':
                console.log(`driver ${user.id} join the socket server`)
                if (user.objective == "locationUpdate") {
                    console.log(`driver ${user.id} join the location update room`)
                    socket.join([`driver:${user.id}`, 'location'], () => {
                        findClientsSocket(null, null)
                    })
                    driver_room.push(`driver:${user.id}`)

                    /**
                     * When the driver "join" (connect/reconnect) 
                     * send the missing data to them
                     */
                    checkMissingMessage(`driver:${user.id}`)

                    cache.lpush(`driver:${user.id}`, JSON.stringify({ event: "joinResponse", data: { message: 'hello' } }))
                    socket.emit("joinResponse", { message: "hello" })
                }
                break;
            case 'passenger':
                console.log(`passenger ${user.id} join the socket server`)
                if (user.objective == "transcation") {
                    console.log(`passenger ${user.id} joins room transcation:${user.transcationid}`)
                    socket.join(`transcation:${user.transcationid}`, () => {
                        findClientsSocket(null, null)
                    })
                    transaction_room.push(`transcation:${user.transcationid}`)
                    /**
                     * When the passenger "join" (connect/reconnect) 
                     * send the missing data to them
                     */
                    if (cache.llen(`transcation:${user.transcationid}`) > 0) {
                        for (let i = 0; i < cache.llen(`transcation:${user.transcationid}`) > 0; ++i) {
                            let message = JSON.parse(cache.lindex(`transcation:${user.transcationid}`, i));
                            socket.broadcast.in(`transcation:${user.transcationid}`).emit(message.event, { transcation: message.transcation, driver: message.driver })
                        }
                    }
                }
                break;
            case 'QRCode':
                console.log(`QR code ${user.platenumber}`)
                socket.join(`qrcode:${user.platenumber}`)
                qrcode_room.push(`qrcode:${user.platenumber}`)
                break;
            case 'admin':
                console.log('admin join the server')
                socket.join('admin')
                break;
            default:
                break;
        }
    })

    //Listen for drivers to update their locations (test client)
    socket.on('locationUpdate', (data) => {
        if (data) {
            // On location update
            // the driver will join the room of location so that the passenger will not receive the broadcast
            socket.broadcast.in("location").emit("location", data)
            io.in('admin').emit("locationUpdate", data)
            // Save the last locations (as hash set) of the drivers into Redis (will later be saved into MySQL by scheduling task)
            cache.hset(data.id, 'latitude', data.location.latitude)
            cache.hset(data.id, 'longitude', data.location.longitude)
            cache.hset(data.id, 'timestamp', data.location.timestamp)
        }
    })

    // Callback (from the passenger)
    // {response: 0/1, transcation: id, driver: id}
    socket.on("driverFoundResponse", (data) => {
        if (data) {
            // Clear cached message
            cache.llen(`transcation:${data.transcationid}`).then(function (length) {
                if (length > 0) {
                    for (let i = 0; i < length; ++i) {
                        // Set the item with "driverFound" event to "delete"
                        cache.lindex(`transcation:${data.transcationid}`, i).then((redisMessage) => {
                            let message = JSON.parse(redisMessage)
                            if (message.event == "driverFound") {
                                cache.lset(`transcation:${data.transcationid}`, i, "delete")
                            }
                        })
                    }
                    cache.lrem(`transcation:${data.transcationid}`, 0, "delete")
                }
            })
            console.log(data)
            var message = {};
            if (data.response == 1) {
                console.log('passenger accept the offer')
                message = { response: 1, driver: data.driver, transcation: data.transcation }
            } else {
                console.log('passenger reject the offer')
                message = { response: 0, driver: data.driver, transcation: data.transcation }
            }
            // publish to the 'transaction_success' channel
            // send the passenger response to the driver
            // If the passenger accepts, invite the driver to the transaction room
            // If the passenger rejects, assign another driver to the passnger
            io.in(`driver:${data.driver}`).emit("transcationInvitation", message)
            io.in('admin').emit('driverFoundResponse', message)
            pub.publish("passengerResponse", JSON.stringify(message))
        }
    })

    //Callback (from the driver)
    //data: {response: 0/1, transcation: transcation, driver: driver}
    socket.on("passengerFoundResponse", (data) => {
        if (data) {
            console.log("Passenger Found Response")
            console.log(data)
            // client successfully receive message from server
            // remove the pending message from Redis
            cache.llen(`driver:${data.driver.id}`).then((length) => {
                console.log(length)
                for (let i = 0; i < length; ++i) {
                    cache.lindex(`driver:${data.driver.id}`, i).then((redisMessage) => {
                        try {
                            let message = JSON.parse(redisMessage)
                            if (message.event == "passengerFound" && message.event) {
                                cache.lset(`driver:${data.driver.id}`, i, "delete")
                            }
                        } catch (e) {
                        }
                    })
                }
                cache.lrem(`driver:${data.driver.id}`, 0, "delete")
            })

            // The driver accept the order and send the request to the passenger
            if (data.response == 1) {
                console.log("Driver accepted the offer")
                pub.publish('driverResponse', JSON.stringify({ response: 1, transcation: data.transcation.id, driver: data.driver.id }))
                io.in('admin').emit('passengerFoundResponse', { response: 1, transcation: data.transcation.id, driver: data.driver.id })
            } else {
                // publish the redis "transcation" channel
                // restart the searching process again
                console.log("Driver reject the offer")
                pub.publish('driverResponse', JSON.stringify({ response: 0, transcation: data.transcation.id, driver: data.driver.id }))
                io.in('admin').emit('passengerFoundResponse', { response: 0, transcation: data.transcation.id, driver: data.driver.id })
            }
        }
    })

    /**
     * data: {key: room, event: driverFound/passengerFound}
     */
    socket.on('eventAck', (data) => {
        console.log('eventAck')
        console.log('event:' + data.event)
        console.log('key: ' + data.key)
        if (data) {
            removeMessageCache(data.key, data.event)
        }
    })

    socket.on("joinTranscation", (data) => {
        console.log("Join transcation")
        console.log(data)
        //join the driver room and transaction room    
        socket.join([`driver:${data.driver}`, `transcation:${data.transcation}`])
        //Driver and passengers are free to communicate now
    })

    socket.on("locationUpdateToPassenger", (data) => {
        io.in(`transcation:${data.passenger}`).emit("locationUpdate", data.pack)
    })


    /**
     * Share ride 
     * Driver will response on whether to accept the deal
     * data:{response: 0/1, transcation: transcationID, driver: driverID}
     */
    socket.on('shareRideDriverResponse', (data) => {
        console.log(data)
        let driverKey = `driver:${data.driver}`
        removeMessageCache(driverKey, "shareRideDriverFound")
        // if the driver accepted the call
        // if(data.response == 1) {
        //     let passenger1Key = `transcation:${data.shareRideTranscation.first_transaction.id}`
        //     let passenger2Key = `transcation:${data.shareRideTranscation.second_transaction.id}`
        //     socket.broadcast.in(passenger1Key).emit("shareRidePairingSuccess", {driver: data.driver, transcation: data.shareRideTranscation});
        //     socket.broadcast.in(passenger2Key).emit("shareRidePairingSuccess", {driver: data.driver, transcation: data.shareRideTranscation});
        // }
        //update the status of the transaction in the database
        pub.publish('shareRideDriverResponse', JSON.stringify({
            response: data.response,
            transcation: data.transcation,
            driver: data.driver
        }))
    })

    /**
     * Share ride
     * Driver accept the call
     * Passengers have to confirm the deal
     * data: {response: 1/0, transaction: shareTransactionID}
     */
    socket.on('shareRidePassengerResponse', (data) => {
        console.log(data)
        pub.publish('shareRidePassengerResponse', JSON.stringify({
            response: data.response,
            transcation: data.transcation,
            shareRideTranscation: data.shareRideTranscation
        }))
    })

    socket.on("disconnect", () => {
        findClientsSocket(null, null)
    })
})

function checkMissingMessage(key) {
    cache.llen(key).then(function (length) {
        if (length > 0) {
            console.log(`${key} has missing messages`)
            for (let i = 0; i < length; ++i) {
                cache.lindex(key, i).then(function (redisMessage) {
                    if (redisMessage != "delete") {
                        try {
                            let message = JSON.parse(redisMessage)
                            io.in(key).emit(message.event, { message: message.data })
                        } catch (e) {
                            console.log(e)
                        }
                    }
                }).catch((e) => {
                    console.log("error: " + e)
                })
            }
        }
    })
}

function removeMessageCache(key, event) {
    cache.llen(key).then((length) => {
        console.log(length)
        for (let i = 0; i < length; ++i) {
            cache.lindex(key, i).then((redisMessage) => {
                console.log(redisMessage)
                try {
                    let message = JSON.parse(redisMessage)
                    if (message.event == event) {
                        cache.lset(key, i, "delete")
                    }
                    cache.lrem(key, 0, "delete")
                } catch (e) {
                    console.log('Parsing Error: ' + e)
                }
            })
        }
        cache.lrem(key, 0, "delete")
    })
}

function saveMessageInCache(key, event, message) {
    var oldDateObj = new Date
    var newDateObj = new Date(oldDateObj.getTime() + 3 * 60000);
    cache.lpush(key, { event: event, data: message, timeout: newDateObj.toISOString.slice(0, 19).replace('T', ' ') })
}

function findClientsSocket(roomId, namespace) {
    var res = []
    var ns = io.of(namespace || '')
    if (ns) {
        for (var id in ns.connected) {
            if (roomId) {
                var index = ns.connected[id].rooms.indexOf(roomId)
                if (index !== -1) {
                    res.push(ns.connected[id])
                }
            } else {
                res.push(ns.connected[id])
                let rooms = Object.keys(ns.connected[id].rooms);
                console.log(rooms)
            }
        }
    }
    return res;
}

redis.subscribe('driverFound', 'driverNotification', 'qrcodeRefresh', 'passengerNotification', function (err, count) {
    if (err) console.log(err)
})

redis.on('message', function (channel, message) {
    console.log(`Received Channel: ${channel}`)
    console.log(`Message received: ${message}`)
    if (message) {
        switch (channel) {
            case 'driverFound':
                var dataPack = JSON.parse(message)
                let driverRoom = `driver:${dataPack.data.driverResource.id}`
                // Send the transaction data to the drivers
                io.in(driverRoom).emit('passengerFound', { transcation: dataPack.data.transcationResource, driver: dataPack.data.driverResource })
                io.in('admin').emit('passengerFound', { transcation: dataPack.data.transcationResource, driver: dataPack.data.driverResource })
                cache.lpush(driverRoom, JSON.stringify({ event: "passengerFound", transcation: dataPack.data.transcationResource, driver: dataPack.data.driverResource }))
                break;

            case 'passengerNotification':   //Push notification to passenger
                let passengerData = JSON.parse(message).data
                console.log(passengerData.event)
                switch (passengerData.event) {
                    case 'passengerDriverFound': // Passenger Personal Ride Driver found event
                        console.log('passengerDriverFound')
                        let transactionRoom = `transcation:${passengerData.transactionResource.id}`
                        // Send message to passenger
                        io.in(transactionRoom).emit(passengerData.event,
                            { transcation: passengerData.transcationResource, driver: passengerData.driverResource })
                        // Save the message in case the passenger cannot receive
                        cache.lpush(transactionRoom,
                            JSON.stringify({ event: "driverFound", transcation: passengerData.transcationResource, driver: passengerData.driverResource }))
                        break;
                    case 'passengerDriverReach': // Personal Ride: driver reach the pick-up point
                        console.log('passengerDriverReach')
                        io.in(`transcation:${passengerData.transcation.id}`)
                            .emit('passengerDriverReach', {
                                transcation: passengerData.transcation,
                                driver: passengerData.driver,
                                time: passengerData.time
                            })
                        cache.lpush(`transcation:${passengerData.transcation.id}`,
                            JSON.stringify({
                                event: "passengerDriverReach",
                                transcation: passengerData.transcation,
                                driver: passengerData.driver,
                                time: passengerData.time
                            }))
                        break;
                    case 'passengerShareRideFound': // Passenger Share Ride Driver found event
                        let first_transaction = passengerData.shareRideTranscation.first_transaction.id
                        let second_transaction = passengerData.shareRideTranscation.second_transaction.id
                        io.in(`transcation:${first_transaction}`)
                            .emit('shareRidePairingSuccess', { transcation: passengerData.shareRideTranscation, driver: passengerData.driver })
                        io.in(`transcation:${second_transaction}`)
                            .emit('shareRidePairingSuccess', { transcation: passengerData.shareRideTranscation, driver: passengerData.driver })
                        cache.lpush(`transcation:${first_transaction}`,
                            JSON.stringify({ event: 'shareRidePairingSuccess', transcation: passengerData.shareRideTranscation, driver: passengerData.driver }))
                        cache.lpush(`transcation:${second_transaction}`,
                            JSON.stringify({ event: 'shareRidePairingSuccess', transcation: passengerData.shareRideTranscation, driver: passengerData.driver }))
                        break;
                }
                break;

            case 'qrcodeRefresh':
                let qrData = JSON.parse(message).data
                io.in(`qrcode:${qrData.platenumber}`).emit('qrcodeRefresh', { accessToken: qrData.accessToken });
                break;

            case 'driverNotification':  //Push notification to driver
                let driverData = JSON.parse(message).data
                switch (driverData.event) {
                    case "PassengerTimeout":
                        let driverKey = `driver:${driverData.driverResource.id}`
                        io.in(driverKey).emit(driverData.event, { data: driverData.transactionResource })
                        cache.lpush(driverKey, JSON.stringify({ event: driverData.event, data: driverData.transactionResource }))
                        break;
                    case "shareRideDriverFound":
                        let driverRoom = `driver:${driverData.driver.id}`;
                        io.in(driverRoom).emit("shareRideDriverFound",
                            { transcation: driverData.shareRideTranscation, driver: driverData.driver })
                        cache.lpush(driverRoom, JSON.stringify({ event: "shareRideDriverFound", data: driverData.shareRideTranscation }))
                        break;
                    default:
                        break;
                }
        }
    }
})

server.listen(3000, function () {
    console.log('Listening on Port 3000')
})