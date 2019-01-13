Command to run for this application
1. ">php "c:\xampp\htdocs\taxi-server\taxi-server\artisan" serve --host=192.168.86.183 --port=8000": start Web server

2. "php artisan queue:listen --tries=3": open queue listener (listen to broadcast and job) (with 3 trials before failure)

3. start redis 
Run the command (in command prompt) ./redis-server.exe redis.windows.conf

4. subscribe to Redis 1
php artisan redis:subscribe

5. subscribe to Redis 2
php artisan redis:passenger

4. start laravel echo server (receive broadcast)
(run "npm run dev" after making any change in resources file)
laravel-echo-server start

5. "node server.js": start websocket server

7. Task schedular
"c:\xampp\htdocs\taxi-server\taxi-server\artisan" schedule:run

6. Broadcast event
Step 1: Define new event
Step 2: define new channel in channel.php

** config/app.php: uncomment App\Providers\BroadcastServiceProvider::class,