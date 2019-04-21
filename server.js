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
                    checkMissingMessage(`driver:${user.id}`)
                }
                break;
            case 'passenger':
                console.log(`passenger ${user.id} join the socket server`)
                console.log(`passenger ${user.id} joins room transcation:${user.transcationid}`)
                socket.join(`transcation:${user.transcationid}`, () => {
                    findClientsSocket(null, null)
                })
                socket.join(`passenger:${user.id}`, () => {
                    findClientsSocket(null, null)
                })
                checkMissingMessage(`passenger:${user.id}`)
                checkMissingMessage(`transcation:${user.transcationid}`)
                break;
            case 'QRCode':
                console.log(`QR code ${user.platenumber}`)
                socket.join(`qrcode:${user.platenumber}`)
                break;
            case 'admin':
                console.log('admin join the server')
                socket.join('admin', () => {
                    findClientsSocket(null, null)
                })
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

    //Response (from the driver)
    //data: {response: 0/1, transcation: transcation, driver: driver}
    socket.on("passengerFoundResponse", (data) => {
        if (data) {
            console.log("Passenger Found Response")
            console.log(data)
            // The driver accept the order and send the request to the passenger
            if (data.response == 1) {
                console.log("Driver accepted the offer")
                console.log(JSON.stringify({ response: 1, transcation: data.transcation, driver: data.driver }))
                pub.publish('driverResponse', JSON.stringify({ response: 1, transcation: data.transcation, driver: data.driver }))
                io.in('admin').emit('passengerFoundResponse', { response: 1, transcation: data.transcation, driver: data.driver })
            } else {
                // publish the redis "transcation" channel
                // restart the searching process again
                console.log("Driver reject the offer")
                pub.publish('driverResponse', JSON.stringify({ response: 0, transcation: data.transcation, driver: data.driver }))
                io.in('admin').emit('passengerFoundResponse', { response: 0, transcation: data.transcation, driver: data.driver })
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

    socket.on("locationUpdateToPassenger", (data) => {
        io.in(`${data.key}`).emit("locationUpdate", data.pack)
    })

    /**
     * Share ride 
     * Driver will response on whether to accept the deal
     * data:{response: 0/1, transcation: transcationID, driver: driverID}
     */
    socket.on('shareRideDriverResponse', (data) => {
        console.log('shareRideDriverResponse')
        console.log(data)
        //update the status of the transaction in the database
        pub.publish('shareRideDriverResponse', JSON.stringify({
            response: data.response,
            transcation: data.transcation,
            driver: data.driver
        }))
    })

    socket.on("getDriverLocation", function (data, fn) {
        console.log(data)
        cache.hgetall(data.driver, function (err, res) {
            console.log(res)
            if(res) {
                fn({
                    latitude: res.latitude, 
                    longitude: res.longitude,
                    timestamp: res.timestamp
                })
            }
        })
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
                            switch (message.event) {
                                case "passengerFound": //To driver
                                    io.in(key).emit(message.event, {
                                        transcation: message.transcation,
                                        driver: message.driver,
                                        time: message.time
                                    })
                                    break;
                                case "shareRidePairingSuccess": // To passenger
                                    io.in(key).emit(message.event,
                                        {
                                            first_transcation: message.first_transcation,
                                            second_transcation: message.second_transcation
                                        })
                                    break;
                                case "PassengerTimeout": // To driver
                                    io.in(key).emit(message.event, {
                                        transcation: message.data.transcation,
                                        driver: message.data.driver
                                    })
                                    break;
                                case "shareRideDriverReach":// to passenger
                                    io.in(key).emit("shareRideDriverReach", {
                                        time: message.time,
                                        transcation: message.transcation
                                    })
                                    break;
                            }
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
    console.log("----------------Session-------------")
    if (ns) {
        for (var id in ns.connected) {
            if (roomId) {
                var index = ns.connected[id].rooms.indexOf(roomId)
                if (index !== -1) {
                    res.push(ns.connected[id])
                }
            } else {
                let rooms = Object.keys(ns.connected[id].rooms);
                rooms.forEach(room => {
                    console.log(room)
                    if (room.indexOf('driver') !== -1) {
                        res.push(room)
                    }
                })
            }
        }
    }
    io.in('admin').emit("onlineUserUpdate", { data: res });
    return res;
}

redis.subscribe('driverFound', 'driverNotification', 'qrcodeRefresh', 'passengerNotification', 'admin', function (err, count) {
    if (err) console.log(err)
})

redis.on('message', function (channel, message) {
    console.log(`Received Channel: ${channel}`)
    console.log(`Message received: ${message}`)
    if (message) {
        switch (channel) {
            case 'admin':
                switch (JSON.parse(message).data.event) {
                    case "transactionUpdate":
                        io.in('admin').emit("transactionUpdate", { transaction: JSON.parse(message).data.transcation })
                        break;
                    case 'driverComparison':
                        io.in('admin').emit('driverComparison', {
                            transaction: JSON.parse(message).data.transcation,
                            drivers: JSON.parse(message).data.driver
                        })
                        break;
                    case 'shareRide':
                        console.log('shareRide')
                        io.in('admin').emit('shareRideUpdate', {
                            event: 'shareRide'
                        })
                        break;
                    case 'shareRideTransaction':
                        console.log('shareRideTransaction')
                        io.in('admin').emit('shareRideUpdate', {
                            event: 'shareRideTransaction'
                        })
                        break;
                    default:
                        break;
                }

                break;
            case 'driverFound':
                var dataPack = JSON.parse(message).data
                let driverRoom = `driver:${dataPack.driverResource.id}`
                // Send the transaction data to the drivers
                io.in(driverRoom).emit('passengerFound', {
                    time: dataPack.time,
                    transcation: dataPack.transcationResource,
                    driver: dataPack.driverResource
                })
                io.in('admin').emit('passengerFound', {
                    time: dataPack.time,
                    transcation: dataPack.transcationResource,
                    driver: dataPack.driverResource
                })
                cache.lpush(driverRoom, JSON.stringify({
                    event: "passengerFound",
                    time: dataPack.time,
                    transcation: dataPack.transcationResource,
                    driver: dataPack.driverResource
                }))
                break;

            case 'passengerNotification':   //Push notification to passenger
                let passengerData = JSON.parse(message).data
                console.log(passengerData.event)
                switch (passengerData.event) {
                    case 'passengerDriverFound': // Passenger Personal Ride Driver found event
                        console.log('passengerDriverFound')
                        let passengerRoom = `passenger:${passengerData.transcationResource.user.id}`
                        // Send message to passenger
                        io.in(passengerRoom).emit(passengerData.event, {
                            transcation: passengerData.transcationResource,
                            driver: passengerData.driverResource,
                            time: passengerData.time
                        })
                        // Save the message in case the passenger cannot receive
                        cache.lpush(passengerRoom,
                            JSON.stringify({
                                event: "passengerDriverFound",
                                transcation: passengerData.transcationResource,
                                driver: passengerData.driverResource,
                                time: passengerData.time
                            }))
                        break;

                    case 'passengerDriverReach': // Personal Ride: driver reach the pick-up point
                        console.log('passengerDriverReach')
                        io.in(`passenger:${passengerData.transcation.user.id}`)
                            .emit('passengerDriverReach', {
                                transcation: passengerData.transcation,
                                driver: passengerData.driver,
                                time: passengerData.time
                            })
                        cache.lpush(`passenger:${passengerData.transcation.id}`,
                            JSON.stringify({
                                event: "passengerDriverReach",
                                transcation: passengerData.transcation,
                                driver: passengerData.driver,
                                time: passengerData.time
                            }))
                        break;

                    case "shareRidePairingSuccess": // From ShareRidePassengerEvent.php
                        let first_passenger = passengerData.shareRideTranscation.first_transaction.user.id
                        let second_passenger = passengerData.shareRideTranscation.second_transaction.user.id
                        console.log(first_passenger)
                        console.log(second_passenger)
                        io.in(`passenger:${first_passenger}`).emit('shareRidePairingSuccess', {
                            transcation: passengerData.shareRideTranscation
                        })
                        io.in(`passenger:${second_passenger}`).emit('shareRidePairingSuccess', {
                            transcation: passengerData.shareRideTranscation
                        })
                        cache.lpush(`passenger:${second_passenger}`,
                            JSON.stringify({
                                event: 'shareRidePairingSuccess',
                                transcation: passengerData.shareRideTranscation
                            }))
                        cache.lpush(`passenger:${first_passenger}`,
                            JSON.stringify({
                                event: 'shareRidePairingSuccess',
                                transcation: passengerData.shareRideTranscation
                            }))
                        break;

                    case "shareRideDriverReach":
                        io.in(`passenger:${passengerData.passengerId}`).emit("shareRideDriverReach", {
                            transcation: passengerData.transcation,
                            time: passengerData.time
                        })
                        cache.lpush(`passenger:${passengerData.passengerId}`, JSON.stringify({
                            event: "shareRideDriverReach",
                            transcation: passengerData.transcation,
                            time: passengerData.time
                        }))
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
                    case "cancelRide":
                        io.in(`driver:${driverData.driver.id}`).emit("cancelRide", { transcation: driverData.transcation })
                    case "PassengerTimeout":
                        let driverKey = `driver:${driverData.driverResource.id}`
                        io.in(driverKey).emit(driverData.event, { data: driverData.transactionResource })
                        cache.lpush(driverKey, JSON.stringify({ event: driverData.event, data: driverData.transactionResource }))
                        break;
                    case "shareRideDriverFound": //Share Ride -> Driver
                        console.log("shareRideDriverFound")
                        let driverRoom = `driver:${driverData.driver.id}`;
                        io.in(driverRoom).emit("shareRideDriverFound", {
                            transcation: driverData.shareRideTranscation,
                            driver: driverData.driver,
                            time: driverData.time
                        })
                        cache.lpush(driverRoom, JSON.stringify({ event: "shareRideDriverFound", data: driverData.shareRideTranscation }))
                        break;
                    case "transcationInvitation":
                        console.log("transactionInvitation");
                        let key = `driver:${driverData.driver.id}`
                        io.in(key).emit("transcationInvitation", {
                            driver: driverData.driver,
                            transcation: driverData.transcation,
                            response: driverData.response
                        })
                    default:
                        break;
                }
        }
    }
})

server.listen(3000, function () {
    console.log('Listening on Port 3000')
})