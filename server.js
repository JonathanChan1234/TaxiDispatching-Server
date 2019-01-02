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

//Call when there is someone connected to the server
io.sockets.on('connection', (socket) => {
    console.log('user connected')
    
    //Listen for user(passenger/driver) joining the server
    socket.on('join', (user) => {
        console.log(user)
        if(user.identity == 'driver') {
            console.log(`driver ${user.id} join the socket server`)
            if(user.objective == "locationUpdate") {
                console.log(`driver ${user.id} join the location update room`)
                socket.join(["location", `driver:${user.id}`])
                driver_room.push(`driver${user.id}`)

                /**
                 * When the driver "join" (connect/reconnect) 
                 * send the missing data to them
                 */
                if(cache.llen(`driver:${user.id}`) > 0) {
                    for(let i = 0; i < cache.llen(`driver:${user.id}`) > 0; ++i) {
                        let message = JSON.parse(cache.lindex(`:${user.id}`, i));
                        socket.broadcast.in(`driver:${user.id}`).emit(message.event, {transcation: message.transcation, driver: message.driver})
                    }
                }
            }
        } else if(user.identity == 'passenger') {
            console.log(`passenger ${user.id} join the socket server`)
            if(user.objective == "transcation") {
                console.log(`passenger ${user.id} joins room transcation:${user.transcationid}`)
                socket.join(`transcation:${user.transcationid}`)
                transaction_room.push(`transcation:${user.transcationid}`)
                /**
                 * When the passenger "join" (connect/reconnect) 
                 * send the missing data to them
                 */
                if(cache.llen(`transcation:${user.transcationid}`) > 0) {
                    for(let i = 0; i < cache.llen(`transcation:${user.transcationid}`) > 0; ++i) {
                        let message = JSON.parse(cache.lindex(`transcation:${user.transcationid}`, i));
                        socket.broadcast.in(`transcation:${user.transcationid}`).emit(message.event, {transcation: message.transcation, driver: message.driver})
                    }
                }
            }
        }
    })

    //Listen for driver to update their location
    socket.on('locationUpdate', (data) => {
        if(data) {
            console.log(data)
            // On location update
            // the driver will join the room of location so that the passenger will not  receive the broadcast
            socket.broadcast.in("location").emit("location", data)
            // Save the last locations (as hash set) of the drivers into Redis (will later be saved into MySQL by scheduling task)
            cache.hset(data.id, 'latitude', data.data[4].latitude)
            cache.hset(data.id, 'longitude',  data.data[4].longitude)
            cache.hset(data.id, 'timestamp', data.data[4].timestamp)
        }
    })

    //Callback (driver found event)
    socket.on("driverFoundResponse", (data) => {
        if(data) {
            if(data.response == 1) {
                // client successfully receive from the client
                // remove the message from Redis
                if(cache.llen(`transcation:${data.transcationid}`) > 0) {
                    // Set the item with "driverFound" event to "delete"
                    for(let i = 0; i < cache.llen(`transcation:${data.transcationid}`) > 0; ++i) {
                        let message = JSON.parse(data.lindex(`transcation:${data.transcationid}`, i));
                        if(message.event == "driverFound") {
                            cache.lset(`transcation:${data.transcationid}`, i, "delete")
                        }
                    }   //End of for loop
                    // Delete all the item with the value "delete"
                    cache.lrem(`transcation:${data.transcationid}`, 0, "delete")
                }
            }
        }
    })

    //Callback (passenger found event)
    socket.on("passengerFoundResponse", (data) => {
        if(data) {
            if(data.response == 1) {
                // client successfully receive from the client
                // remove the message from Redis
                if(cache.llen(`driver:${data.id}`) > 0) {
                    // Set the item with "driverFound" event to "delete"
                    for(let i = 0; i < cache.llen(`driver:${data.id}`) > 0; ++i) {
                        let message = JSON.parse(cache.lindex(`driver:${user.id}`, i));
                        if(message.event == "driverFound") {
                            cache.lset(`driver:${user.id}`, i, "delete")
                        }
                    }   //End of for loop
                    // Delete all the item with the value "delete"
                    cache.lrem(`driver:${user.id}`, 0, "delete")
                }
            }
        }
    })
})

redis.subscribe('driverFound', 'notification', function(err, count) {
    if(err) console.log(err)
})

redis.on('message', function(channel, message) {
    console.log(`Received Channel: ${channel}`)
    console.log(`Message received: ${message}`)
    if(message) {
        var dataPack = JSON.parse(message)
        let driverRoom = `driver:${dataPack.data.driverResource.id}`
        let transactionRoom = `transcation:${dataPack.data.transcationResource.id}`
        // Send the driver data to the passengers
        // Store the data to temporary storage
        io.in(transactionRoom).emit('driverFound', {transcation: dataPack.data.transcationResource, driver: dataPack.data.driverResource})
        cache.lpush(transactionRoom, JSON.stringify({event: "driverFound", transcation: dataPack.data.transcationResource, driver: dataPack.data.driverResource}))
        // Send the transaction data to the drivers
        io.in(driverRoom).emit('passengerFound', {transcation: dataPack.data.transcationResource, driver: dataPack.data.driverResource})
        cache.lpush(driverRoom, JSON.stringify({event: "passengerFound", transcation: dataPack.data.transcationResource, driver: dataPack.data.driverResource}))
    }
})

server.listen(3000, function() {
    console.log('Listening on Port 3000')
})