const io = require('socket.io-client')
const socket = io('http://localhost:3000')
const gpxParse = require('gpx-parse')

var location_points = [
    { latitude: 22.312307, longitude: 114.162917, timestamp: '2019-01-02 12:08:07' },
    { latitude: 22.315429, longitude: 114.160813, timestamp: '2019-01-02 12:08:07' },
    { latitude: 22.318510, longitude: 114.160251, timestamp: '2019-01-02 12:08:07' },
    { latitude: 22.323433, longitude: 114.156700, timestamp: '2019-01-02 12:08:07' },
    { latitude: 22.327730, longitude: 114.152301, timestamp: '2019-01-02 12:08:07' },
    { latitude: 22.333228, longitude: 114.146786, timestamp: '2019-01-02 12:08:07' },
    { latitude: 22.335630, longitude: 114.149511, timestamp: '2019-01-02 12:08:07' },
]

var i = 0

var fs = require('fs')
var tj = require('@mapbox/togeojson')
var DOMParser = require('xmldom').DOMParser
var kml = new DOMParser().parseFromString(fs.readFileSync('./kml/RL-2019-01-19-2251.kml', 'utf-8'))
var converted = tj.kml(kml)
var id = 4

//On Connecting to the server
socket.on('connect', () => {
    console.log('connected to the server')
    socket.emit("join", { identity: 'driver', id: id, objective: "locationUpdate" })
    location_update()
})

socket.on('joinResponse', (data) => {
    console.log(data)
    socket.emit('eventAck', {key: "driver:4", event: "joinResponse"})
})

//On Disconnecting to the server
socket.on('disconnect', () => {
    console.log("disconnected to server")
})

//On passenger found event 
socket.on('passengerFound', (data) => {
    console.log(data)
    console.log("passenger found")
    //Pass three data (transcation, driver and taxi)
    socket.emit('passengerFoundResponse', { response: 1, transcation: data.transcation, driver: data.driver })
})

// On success transaction(confirmation by both driver and passenger) 
// Driver join the transaction room
socket.on("transcationInvitation", (data) => {
    if (data) {
        if (data.response == 1) {
            console.log('transcation completed')
            socket.emit("joinTranscation", { driver: data.driver, transcation: data.transcation })
            locationUpdateToPassenger(data.transcation)
        } else {
            console.log('transcation failed')
            console.log('will find the next passenger')
        }
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

// On message from passenger
socket.on('message', (data) => {
    console.log(data)
    if(data.from !== 4) {
        setTimeout(() => {
            socket.emit("message", {target: 5, message: `Re:${data.message}`, from: 4, username: "Sasa"})
        }, 10000)
    }
})

socket.on("PassengerTimeout", (data) => {
    console.log("passenger timeout")
    socket.emit('eventAck', {key: "driver:4", event: "PassengerTimeout"})
})

/**
 * Share ride event
 * share ride is assigned to the driver
 * {transcation: transcation_id, driver: driver_id}
 */
socket.on("shareRideDriverFound", (data) => {
    console.log("Share Ride found");
    console.log(data);
    socket.emit('eventAck', {event: "shareRideDriverFound", key: 'driver:4'})
    if(data) {
        socket.emit("shareRideDriverResponse", {response: 1, transcation: data.transcation.id, driver: data.driver.id})
    }
})

// Reporting position to the passenger
function locationUpdateToPassenger(transcation) {
    setTimeout(function () {
        locationTimer = setInterval(function () {
            locationData = { latitude: converted.features[0].geometry.coordinates[i][1], longitude: converted.features[0].geometry.coordinates[i][0], timestamp: '2019-01-02 12:08:07' }
            socket.emit("locationUpdateToPassenger", { passenger: transcation, pack: { id: id, location: locationData } })
            i++
            if (i == converted.features[0].geometry.coordinates.length) i = 0
        }, 5000)
    }, 10000)
}

// Reporting position to the server
function location_update() {
    setTimeout(function () {
        locationTimer = setInterval(function () {
            locationData = { latitude: converted.features[0].geometry.coordinates[i][1], longitude: converted.features[0].geometry.coordinates[i][0], timestamp: '2019-01-02 12:08:07' }
            socket.emit("locationUpdate", { id: id, location: locationData })
            i++
            if (i == converted.features[0].geometry.coordinates.length) i = 0
        }, 10000)
    }, 10000)
}
