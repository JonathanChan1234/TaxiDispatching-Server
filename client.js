const io = require('socket.io-client')
const gpxParse = require('gpx-parse')
const fs = require('fs')
const tj = require('@mapbox/togeojson')
const moment = require('moment-timezone')
var DOMParser = require('xmldom').DOMParser


var route_1 = tj.kml(new DOMParser().parseFromString(fs.readFileSync('./kml/RL-2019-01-19-2251.kml', 'utf-8')))
var route_2 = tj.kml(new DOMParser().parseFromString(fs.readFileSync('./kml/route2.kml', 'utf-8')))
var route_3 = tj.kml(new DOMParser().parseFromString(fs.readFileSync('./kml/route3.kml', 'utf-8')))
var route_4 = tj.kml(new DOMParser().parseFromString(fs.readFileSync('./kml/route4.kml', 'utf-8')))
var route_5 = tj.kml(new DOMParser().parseFromString(fs.readFileSync('./kml/route5.kml', 'utf-8')))
var route_6 = tj.kml(new DOMParser().parseFromString(fs.readFileSync('./kml/route6.kml', 'utf-8')))
var route_7 = tj.kml(new DOMParser().parseFromString(fs.readFileSync('./kml/route7.kml', 'utf-8')))
var route_8 = tj.kml(new DOMParser().parseFromString(fs.readFileSync('./kml/route8.kml', 'utf-8')))
var route_9 = tj.kml(new DOMParser().parseFromString(fs.readFileSync('./kml/route9.kml', 'utf-8')))
var route_10 = tj.kml(new DOMParser().parseFromString(fs.readFileSync('./kml/route10.kml', 'utf-8')))

const socket_driver4 = io('http://localhost:3000')
const socket_driver6 = io('http://localhost:3000')
const socket_driver7 = io('http://localhost:3000')
const socket_driver8 = io('http://localhost:3000')
const socket_driver9 = io('http://localhost:3000')
const socket_driver10 = io('http://localhost:3000')
const socket_driver11 = io('http://localhost:3000')
const socket_driver12 = io('http://localhost:3000')
const socket_driver13 = io('http://localhost:3000')
const socket_driver14 = io('http://localhost:3000')

initSocket(socket_driver4, 4, route_1)
initSocket(socket_driver6, 6, route_2)
initSocket(socket_driver7, 7, route_3)
initSocket(socket_driver8, 8, route_4)
initSocket(socket_driver9, 9, route_5)
initSocket(socket_driver10, 10, route_6)
initSocket(socket_driver11, 13, route_7)
initSocket(socket_driver12, 14, route_8)
initSocket(socket_driver13, 15, route_9)
initSocket(socket_driver14, 16, route_10)

function initSocket(socket, id, route) {
    //On Connecting to the server
    socket.on('connect', () => {
        console.log('connected to the server')
        socket.emit("join", { identity: 'driver', id: id, objective: "locationUpdate" })
        location_update()
    })

    socket.on('joinResponse', (data) => {
        console.log(data)
        socket.emit('eventAck', { key: `driver:${id}`, event: "joinResponse" })
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
        socket.emit('eventAck', { key: `driver:${id}`, event: "passengerFound" })
        socket.emit('passengerFoundResponse', { response: 1, transcation: data.transcation.id, driver: data.driver.id })
    })

    // On success transaction(confirmation by both driver and passenger) 
    // Driver join the transaction room
    socket.on("transcationInvitation", (data) => {
        if (data) {
            socket.emit('transcationInvitation', { key: `driver:${id}`, event: "transcationInvitation" })
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

    socket.on("PassengerTimeout", (data) => {
        console.log("passenger timeout")
        socket.emit('eventAck', { key: `driver:${id}`, event: "PassengerTimeout" })
    })

    /**
     * Share ride event
     * share ride is assigned to the driver
     * {transcation: transcation_id, driver: driver_id}
     */
    socket.on("shareRideDriverFound", (data) => {
        console.log("Share Ride found");
        console.log(data);
        socket.emit('eventAck', { event: "shareRideDriverFound", key: `driver:${id}` })
        if (data) {
            socket.emit("shareRideDriverResponse", { response: 1, transcation: data.transcation.id, driver: data.driver.id })
        }
    })

    // Reporting position to the server
    function location_update() {
        var i = 0;
        console.log("id: "+ id + " " + route.features[0].geometry.coordinates.length)
        locationTimer = setInterval(function () {
            locationData = {
                latitude: route.features[0].geometry.coordinates[i][1],
                longitude: route.features[0].geometry.coordinates[i][0],
                timestamp: getLocalTime()
            }
            socket.emit("locationUpdate", { id: id, location: locationData })
            i++
            if (i == route.features[0].geometry.coordinates.length) i = 0
        }, 1000)
    }
}

function getLocalTime() {
    var date = moment.utc().format()
    var local = moment.utc(date).local().format('YYYY-MM-DD HH:mm:ss')
    return local
}

