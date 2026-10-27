<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Сертификат</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            text-align: center;
            padding: 110px 60px;
            color: #172554;
            border: 12px solid #eef2ff;
        }

        h1 {
            font-size: 34px;
            margin-bottom: 36px;
            color: #4338ca;
        }

        h2 {
            font-size: 28px;
            margin: 30px 0;
            color: #0f172a;
        }

        p {
            font-size: 18px;
        }
    </style>
</head>
<body>
    <h1>Сертификат о прохождении курса</h1>

    <p>Настоящий сертификат подтверждает успешное завершение обучения</p>

    <h2>{{ $student->name }}</h2>

    <p>по программе курса</p>

    <h2>{{ $course->title }}</h2>

    <p>Дата выдачи: {{ now()->format('d.m.Y') }}</p>
</body>
</html>
