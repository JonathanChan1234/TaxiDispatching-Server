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
        if (user.identity == 'driver') {
            console.log(`driver ${user.id} join the socket server`)
            if (user.objective == "locationUpdate") {
                console.log(`driver ${user.id} join the location update room`)
                socket.join([`driver:${user.id}`, 'location'])
                driver_room.push(`driver:${user.id}`)

                /**
                 * When the driver "join" (connect/reconnect) 
                 * send the missing data to them
                 */
                cache.llen(`driver:${user.id}`).then(function (length) {
                    if (length > 0) {
                        console.log(`driver ${user.id} has missing messages`)
                        for (let i = 0; i < length; ++i) {
                            cache.lindex(`driver:${user.id}`, i).then(function (redisMessage) {
                                if (redisMessage != "delete") {
                                    let message = JSON.parse(redisMessage)
                                    // socket.emit(message.event, { transcation: message.transcation, driver: message.driver })
                                    io.in(`driver:${user.id}`).emit(message.event, { transcation: message.transcation, driver: message.driver })
                                }
                            }).catch((e) => {
                                console.log("error: " + e)
                            })
                        }
                    }
                })
            }
        } else if (user.identity == 'passenger') {
            console.log(`passenger ${user.id} join the socket server`)
            if (user.objective == "transcation") {
                console.log(`passenger ${user.id} joins room transcation:${user.transcationid}`)
                socket.join(`transcation:${user.transcationid}`)
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
        } else if(user.identity == 'QRCode') {
            console.log(`QR code ${user.platenumber}`)
            socket.join(`qrcode:${user.platenumber}`)
            qrcode_room.push(`qrcode:${user.platenumber}`)
        }
    })

    //Listen for drivers to update their locations (Mobile client)
    // socket.on('locationUpdate', (data) => {
    //     if (data) {
    //         console.log(data)
    //         // On location update
    //         // the driver will join the room of location so that the passenger will not  receive the broadcast
    //         socket.broadcast.in("location").emit("location", data)
    //         // Save the last locations (as hash set) of the drivers into Redis (will later be saved into MySQL by scheduling task)
    //         cache.hset(data.id, 'latitude', data.data[4].latitude)
    //         cache.hset(data.id, 'longitude', data.data[4].longitude)
    //         cache.hset(data.id, 'timestamp', data.data[4].timestamp)
    //     }
    // })

    //Listen for drivers to update their locations (test client)
    socket.on('locationUpdate', (data) => {
        if (data) {
            console.log('location update from client')
            // On location update
            // the driver will join the room of location so that the passenger will not receive the broadcast
            socket.broadcast.in("location").emit("location", data)
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
            // invite the driver to enter the transcation room
            io.in(`driver:${data.driver}`).emit("transcationInvitation", message)
            pub.publish("passengerResponse", JSON.stringify(message))
        }
    })

    //Callback (from the driver)
    //data: {response: 0/1, transcation: transcation, driver: driver}
    socket.on("passengerFoundResponse", (data) => {
        if (data) {
            console.log("Passenger Found")
            // client successfully receive message from server
            // remove the pending message from Redis
            cache.llen(`driver:${data.driver.id}`).then((length) => {
                console.log(length)
                for (let i = 0; i < length; ++i) {
                    cache.lindex(`driver:${data.driver.id}`, i).then((redisMessage) => {
                        let message = JSON.parse(redisMessage)
                        if (message.event == "passengerFound" && message.event) {
                            cache.lset(`driver:${data.driver.id}`, i, "delete")
                        }
                    })
                }
                cache.lrem(`driver:${data.driver.id}`, 0, "delete")
            })

            // The driver accept the order and send the request to the passenger
            if (data.response == 1) {
                console.log("Driver accepted the offer")
                pub.publish('driverResponse', JSON.stringify({ response: 1, transcation: data.transcation.id, driver: data.driver.id }))
                io.in(`transcation:${data.transcation.id}`).emit('driverFound', { transcation: data.transcation, driver: data.driver })
                cache.lpush(`transcation:${data.transcation.id}`, JSON.stringify({ event: "driverFound", transcation: data.transcation, driver: data.driver }))
            } else {
                // publish the redis "transcation" channel
                // restart the searching process again
                console.log("Driver reject the offer")
                pub.publish('driverResponse', JSON.stringify({ response: 0, transcation: data.transaction.id, driver: data.driver.id }))
            }
        }
    })

    socket.on("joinTranscation", (data) => {
        console.log("Join transcation")
        //join the driver room and transaction room
        socket.join([`driver:${data.driver}`, `transcation:${data.transcation}`])
        //Driver and passengers are free to communicate now
    })
})

redis.subscribe('driverFound', 'notification', 'qrcodeRefresh', function (err, count) {
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
                cache.lpush(driverRoom, JSON.stringify({ event: "passengerFound", transcation: dataPack.data.transcationResource, driver: dataPack.data.driverResource }))
                break
            case 'qrcodeRefresh':
                var data = JSON.parse(message).data
                io.in(`qrcode:${data.platenumber}`).emit('qrcodeRefresh', {accessToken: data.accessToken});
                break;
        }
    }
})

server.listen(3000, function () {
    console.log('Listening on Port 3000')
})