@extends('master')

@section('content')
    <p id="power">0</p>
@stop

@section('footer')
    <!-- <script src="https://cdn.socket.io/socket.io-1.3.5.js"></script> -->
    <!-- <script src="http://localhost:3000/socket.io/socket.io.js"></script> -->
    <script src="{{ mix('js/app.js') }}"></script>
    <script>
        // var socket = io('http://localhost:3000');
        // socket.on("notification:App\\Events\\PushNotification", function(message){
        //     // increase the power everytime we load test route
        //     $('#power').text(parseInt($('#power').text()) + parseInt(message.data.power));
        // });
        console.log(Echo)
        Echo.channel('notification').listen('PushNotification', function(e) {
            console.log(e);
        });
    </script>
@stop