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
var kml = new DOMParser().parseFromString(fs.readFileSync('./kml/route5.kml', 'utf-8'))
var converted = tj.kml(kml)
var id = 10

socket.on('connect', () => {
    console.log('connected to the server')
    socket.emit("join", { identity: 'driver', id: id, objective: "locationUpdate" })
    location_update()
})
socket.on('disconnect', () => {
    console.log("disconnected to server")
})

socket.on('passengerFound', (data) => {
    console.log(data)
    console.log("passenger found")
    //Pass three data (transcation, driver and taxi)
    socket.emit('passengerFoundResponse', { response: 1, transcation: data.transcation, driver: data.driver })
})

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

function location_update() {
    setTimeout(function () {
        locationTimer = setInterval(function () {
            locationData = { latitude: converted.features[0].geometry.coordinates[i][1], longitude: converted.features[0].geometry.coordinates[i][0], timestamp: '2019-01-02 12:08:07' }
            socket.emit("locationUpdate", { id: id, location: locationData })
            i++
            if (i == converted.features[0].geometry.coordinates.length) i = 0
        }, 1000)
    }, 1000)
}
