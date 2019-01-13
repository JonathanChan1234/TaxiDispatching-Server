const io = require('socket.io-client')
const socket = io('http://localhost:3000')
var location_points = [
    {latitude: 22.312307, longitude: 114.162917, timestamp: '2019-01-02 12:08:07'},
    {latitude: 22.315429, longitude: 114.160813, timestamp: '2019-01-02 12:08:07'},
    {latitude: 22.318510, longitude: 114.160251, timestamp: '2019-01-02 12:08:07'},
    {latitude: 22.323433, longitude: 114.156700, timestamp: '2019-01-02 12:08:07'},
    {latitude: 22.327730, longitude: 114.152301, timestamp: '2019-01-02 12:08:07'},
    {latitude: 22.333228, longitude: 114.146786, timestamp: '2019-01-02 12:08:07'},
    {latitude: 22.335630, longitude: 114.149511, timestamp: '2019-01-02 12:08:07'},
]

var i = 0

// var location_points = [
//     {latitude: 22.319875, longitude: 114.165256, timestamp: '2019-01-02 12:08:07'},
//     {latitude: 22.319237, longitude: 114.166087, timestamp: '2019-01-02 12:08:07'},
//     {latitude: 22.321212, longitude: 114.165526, timestamp: '2019-01-02 12:08:07'},
//     {latitude: 22.323398, longitude: 114.165094, timestamp: '2019-01-02 12:08:07'},
//     {latitude: 22.326207, longitude: 114.163487, timestamp: '2019-01-02 12:08:07'},
//     {latitude: 22.326454, longitude: 114.167688, timestamp: '2019-01-02 12:08:07'},
//     {latitude: 22.324598, longitude: 114.170047, timestamp: '2019-01-02 12:08:07'},
// ]

// var location_points = [
//     {latitude: 22.310478, longitude: 114.167226, timestamp: '2019-01-02 12:08:07'},
//     {latitude: 22.315756, longitude: 114.166568, timestamp: '2019-01-02 12:08:07'},
//     {latitude: 22.322332, longitude: 114.165302, timestamp: '2019-01-02 12:08:07'},
//     {latitude: 22.326498, longitude: 114.166706, timestamp: '2019-01-02 12:08:07'},
//     {latitude: 22.324865, longitude: 114.169992, timestamp: '2019-01-02 12:08:07'},
//     {latitude: 22.323927, longitude: 114.167878, timestamp: '2019-01-02 12:08:07'},
//     {latitude: 22.317979, longitude: 114.166240, timestamp: '2019-01-02 12:08:07'},
// ]

// var location_points = [
//     {latitude: 22.301186, longitude: 114.168048, timestamp: '2019-01-02 12:08:07'},
//     {latitude: 22.299171, longitude: 114.169242, timestamp: '2019-01-02 12:08:07'},
//     {latitude: 22.294652, longitude: 114.165302, timestamp: '2019-01-02 12:08:07'},
//     {latitude: 22.295888, longitude: 114.176394, timestamp: '2019-01-02 12:08:07'},
//     {latitude: 22.299321, longitude: 114.181588, timestamp: '2019-01-02 12:08:07'},
//     {latitude: 22.305136, longitude: 114.183260, timestamp: '2019-01-02 12:08:07'},
//     {latitude: 22.306188, longitude: 114.177384, timestamp: '2019-01-02 12:08:07'},
// ]


socket.on('connect', () => {
    console.log('connected to the server')
    socket.emit("join", {identity: 'driver', id: 4, objective: "locationUpdate"})
    location_update()
})
socket.on('disconnect', () => {
    console.log("disconnected to server")
})

socket.on('passengerFound', (data) => {
    console.log(data)
    console.log("passenger found")
    //Pass three data (transcation, driver and taxi)
    socket.emit('passengerFoundResponse', {response: 1, transcation: data.transcation, driver: data.driver})
})

socket.on("transcationInvitation", (data) => {
    if(data) {
        if(data.response == 1) {
            console.log('transcation completed')
            socket.emit("joinTranscation", {driver: data.driver, transcation: data.transcation})
        } else {
            console.log('transcation failed')
            console.log('will find the next passenger')
        }
    }
})

function location_update() {
    setTimeout(function() {
        setInterval(function() {
            socket.emit("locationUpdate", {id: 4, location: location_points[i]})
            i = (++i) % 6
        }, 10000)
    }, 5000)
    
}