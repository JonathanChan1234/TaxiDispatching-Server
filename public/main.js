var map
var users
var drivers = []
var markers = []
var currentEvent = []
var transactionMarker = []

var startMarker = []
var endMarker = []

$(document).ready(function () {
    var socket = io.connect('http://localhost:3000')
    var table = document.getElementById('table')
    var eventList = document.getElementById('eventList')

    socket.on("connect", () => {
        console.log('connected to the server')
        socket.emit("join", { identity: 'admin' })
    })

    socket.on("locationUpdate", (data) => {
        addDriver(data)
        addMarkerOnMap()
        updateTable(table)
    })

    socket.on("passengerFound", (data) => {
        console.log("Passenger Found")
        updateEventList('passengerFound', data, eventList)
    })

    socket.on("passengerFoundResponse", (data) => {
        console.log("Passenger Found Response from the driver")
        updateEventList('passengerFoundResponse', data, eventList)
    })

    socket.on("driverFoundResponse", (data) => {
        updateEventList("driverFoundResponse", data, eventList)
    })

    socket.on("onlineUserUpdate", (data) => {
        console.log(data)
        updateOnlineUserList(data.data);
    })

    $('#resetButton').click(() => {
        $.ajax({
            url: 'http://192.168.86.183:8000/api/driver/resetDriver',
            type: 'post',
            success: function(result, status) {
                alert("Reset all drivers")
            }
        })
    })
})

function updateOnlineUserList(users) {
    var list = '<tr><th>Online Driver</th></tr>'
    if(users.length > 0) {
        users.forEach(user => {
            list += `<tr><td>${user}<td><tr>`
        })
        $('#onlineDriverTable').html(list)
    }
}

function updateEventList(event, data, eventList) {
    switch (event) {
        case "passengerFound":
            let transactionStatement = `Transaction id ${data.transcation.id} ` +
                `is requested by user (id ${data.transcation.user.id}, username ${data.transcation.user.username})`
            let orderStatement = `Pick-up point: ${data.transcation.start_addr} (${data.transcation.start_lat}, ${data.transcation.start_long})\n` + 
            `Destination point： ${data.transcation.des_addr} (${data.transcation.des_lat}, ${data.transcation.des_long})　`
            let driverStatement = `Driver ${data.driver.id} is picked`
            addTransactionMarker(data)
            currentEvent.push(`<p>${transactionStatement}\n${orderStatement}\n${driverStatement}</p>`)
            break
        case "passengerFoundResponse":
            var responseStatement = ''
            if(data.response == 1) {
                responseStatement = `<p>Driver id ${data.driver} accept the transaction ${data.transcation}</p>`
            } else {
                responseStatement = `<p>Driver id ${data.driver} accept the transaction ${data.transcation}</p>`
            }
            currentEvent.push(responseStatement)
            break
        case "driverFoundResponse":
            var responseStatement = ''
            if(data.response == 1) {
                responseStatement = `<p>Passenger accepts the transaction ${data.transcation}</p>`   
            } else {
                responseStatement = `<p>Passenger rejects the transaction ${data.transcation}</p>`
            }
            currentEvent.push(responseStatement)
            break
        default:
            break
    }
    var content = ''
    currentEvent.forEach(value => {
        content += value
    })
    eventList.innerHTML = content
}

function addTransactionMarker(data) {
    var start = new google.maps.Marker({
        position: new google.maps.LatLng(data.transcation.start_lat, data.start_long),
        title: `transaction id ${data.transcation.id}`,
        label: `${data.transcation.id}`,
        icon: {                             
            url: "marker.png"                        
        }
    })
    startMarker.push(start)
    var end = new google.maps.Marker({
        position: new google.maps.LatLng(data.transcation.des_lat, data.des_long),
        title: `transaction id ${data.transcation.id}`,
        label: `${data.transcation.id}`,
        icon: {                             
            url: "marker.png"                           
        }
    })
    endMarker.push(end)
    
    startMarker.forEach((value) => {
        value.setMap(map)
    })
    endMarker.forEach((value) => {
        value.setMap(map)
    })
}

function updateTable(table) {
    var content = '<tr><th>Available Driver ID</th><th>Current Location</th><th>Last Update at</th></tr>'
    for (let i = 0; i < drivers.length; ++i) {
        content += `<tr><th>${drivers[i].id}</th>` +
            `<th>{${drivers[i].location.latitude.toFixed(3)}, ${drivers[i].location.longitude.toFixed(3)}}` +
            `<th>${drivers[i].location.timestamp}</th><tr>`
    }
    table.innerHTML = content
}

function addDriver(newDriver) {
    for (let i = 0; i < drivers.length; ++i) {
        if (drivers[i].id == newDriver.id) {
            console.log("location update from driver " + drivers[i].id)
            drivers[i].location = newDriver.location
            return;
        }
    }
    drivers.push(newDriver)
}

function addMarkerOnMap() {
    deleteMarker()
    drivers.forEach((driver, index) => {
        var marker = new google.maps.Marker({
            position: new google.maps.LatLng(driver.location.latitude, driver.location.longitude),
            title: `driver ${driver.id}`,
            label: `driver ${driver.id}`,
            icon: {
                url: "vehicle.png"
            }
        })
        markers.push(marker)
    })
    setMapOnAll(map)
}

function setMapOnAll(map) {
    for (var i = 0; i < markers.length; i++) {
        markers[i].setMap(map);
    }
}

function deleteMarker() {
    setMapOnAll(null)
    markers = []
}

function initMap() {
    map = new google.maps.Map(document.getElementById('map'), {
        center: { lat: 22.317426, lng: 114.164174 },
        zoom: 16
    });
}