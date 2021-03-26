<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <!-- HTML Meta Tags -->
        <title>It's Cat Time</title>
        <meta name="description" content="ItsCatTime">

        <!-- Google / Search Engine Tags -->
        <meta itemprop="name" content="ItsCatTime">
        <meta itemprop="description" content="ItsCatTime, a friendly Twitch chat bot">
        <meta itemprop="image" content="https://twitch.jake.cat/img/1f638.png">

        <!-- Facebook Meta Tags -->
        <meta property="og:url" content="https://twitch.jake.cat">
        <meta property="og:type" content="website">
        <meta property="og:title" content="ItsCatTime">
        <meta property="og:description" content="ItsCatTime, a friendly Twitch chat bot">
        <meta property="og:image" content="https://twitch.jake.cat/img/1f638.png">

        <!-- Twitter Meta Tags -->
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="ItsCatTime">
        <meta name="twitter:description" content="ItsCatTime, a friendly Twitch chat bot">
        <meta name="twitter:image" content="https://twitch.jake.cat/img/1f638.png">

        <!-- Meta Tags Generated via http://heymeta.com -->


        <link href="{{ asset('css/app.css') }}" rel="stylesheet">

        <!-- Fonts -->
        <link href="https://fonts.googleapis.com/css?family=Nunito:200,600" rel="stylesheet">

        <!-- Styles -->
        <style>
            html, body {
                background-color: #54c1a9;
                color: #636b6f;
                font-family: 'Nunito', sans-serif;
                font-weight: 200;
                height: 100vh;
                margin: 0;
            }

            .full-height {
                height: 100vh;
            }

            .flex-center {
                align-items: center;
                display: flex;
                justify-content: center;
            }

            .position-ref {
                position: relative;
            }

            .top-right {
                position: absolute;
                right: 10px;
                top: 18px;
            }

            .content {
                text-align: center;
            }

            .title {
                font-size: 84px;
            }

            .links > a {
                color: #636b6f;
                padding: 0 25px;
                font-size: 13px;
                font-weight: 600;
                letter-spacing: .1rem;
                text-decoration: none;
                text-transform: uppercase;
            }

            .m-b-md {
                margin-bottom: 30px;
            }

            .meow {
                max-width: 90%;
            }
        </style>
    </head>
    <body>
        <div class="flex-center position-ref full-height">
            <div class="content">
                @yield('content')
            </div>
        </div>
    </body>
</html>
