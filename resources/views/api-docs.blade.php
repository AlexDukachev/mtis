<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>MTIS API</title>
    @vite(['resources/js/api-docs.js'])
</head>

<body style="margin:0">
    <div id="swagger-ui"></div>
</body>

</html>
