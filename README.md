1. "laravel artisan serve": start Web server

2. "php artisan queue:listen --tries=3": open queue listener (listen to broadcast and job) (with 3 trials before failure)

3. start redis 
Run the command (in command prompt) ./redis-server.exe redis.windows.conf

4. subscribe to redis php artisan redis:subscribe

4. "laravel-echo-server start": open laravel echo server (receive broadcast)
(run "npm run dev" after making any change in resources file)

5. "node server.js": start websocket server

6. Broadcast event
Step 1: Define new event
Step 2: define new channel in channel.php

** config/app.php: uncomment App\Providers\BroadcastServiceProvider::class,