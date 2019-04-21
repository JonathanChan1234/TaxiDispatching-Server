var io = require('socket.io-client')
var socket = io.connect('http://localhost:3000/', {
    reconnect: true
})
socket.on('connect', () => {
    console.log('Connected to the server')
    socket.emit('join', {identity: "passenger", id: 4})
    locationUpdate()
})

socket.on('test', (data) =>{
    console.log(data)
})

socket.on('message', (data) => {
    console.log(data)
    if(data.from !== 4) {
        setTimeout(() => {
            socket.emit("message", {target: 5, message: `Re:${data.message}`, from: 4, username: "Sasa"})
        }, 10000)
    }
})

socket.on('locationMessage', (data) => {
    console.log(data)
    if(data.from != 4) {
        setTimeout(() => {
            socket.emit("locationMessage", {target: 5, location: {latitude: 22.286959, longitude: 114.151005}, from: 4, username: "Sasa"})
        }, 10000)
    }
})

socket.on('locationResponse', (data) => {
    console.log(data)
}) 

function locationUpdate() {
    setInterval(()=> {
        socket.emit("getLocation", {driver: 4})
    }, 2000)
}