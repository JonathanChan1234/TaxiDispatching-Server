const express = require('express'),
    http = require('http'),
    app = express(),
    server = http.createServer(app),
    io = require('socket.io').listen(server)
var nsp = io.of('/passenger')
var connected_clients = new Array();
// nsp.on('connection', (socket) => {
//     console.log('passenger join the server')

//     socket.on('join', (message) => {
//         findClientsSocket(null, 'passenger')
//     })

//     socket.on('disconnect', (message) => {

//     })
// })


io.sockets.on('connection', (socket) => {
    console.log('new user connected')

    socket.on('join', (message) => {
        console.log(`${message.identity}:${message.id}`)
        socket.join(`${message.identity}:${message.id}`, () => {
            let rooms = Object.keys(socket.rooms)
            // console.log(rooms)
            findClientsSocket(null, null)
            messageTest(socket, message)
        })
    })

    /**
     * message data
     * {target: id, message: message, from: id, username: username}
     */
    socket.on('message', (message) => {
        console.log(message)
        io.in(`passenger:${message.target}`).emit('message', message)
        io.in(`passenger:${message.from}`).emit('message', message)
    })

    socket.on('locationMessage', (message) => {
        console.log(message)
        io.in(`passenger:${message.target}`).emit('locationMessage', message)
        io.in(`passenger:${message.from}`).emit('locationMessage', message)
    })

    socket.on('locationUpdateToPassenger', (data) => {
        console.log("On location update")
        // socket.emit('locationResponse', data)
        io.in(`passenger:${data.passenger}`).emit('locationUpdateFromDriver', data)
    })

    socket.on('disconnect', () => {
        console.log('user disconnect')
        findClientsSocket(null, null)
    })
})

function messageTest(socket, data) {
    setInterval(() => {
        socket.emit('locationResponse', data)
    }, 2000)
}
server.listen(3000, function () {
    console.log('Listening on Port 3000')
})

function findClientsSocket(roomId, namespace) {
    var res = []
    var ns = io.of(namespace || '')
    if(ns) {
        for(var id in ns.connected) {
            if(roomId) {
                var index = ns.connected[id].rooms.indexOf(roomId)
                if(index !== -1) {
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