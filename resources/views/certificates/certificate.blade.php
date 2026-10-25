<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            text-align: center;
            padding: 120px 60px;
        }

        h1 {
            font-size: 36px;
            margin-bottom: 40px;
        }

        h2 {
            font-size: 28px;
            margin: 30px 0;
        }

        p {
            font-size: 18px;
        }
    </style>
</head>
<body>
    <h1>Certificate of Completion</h1>

    <p>This certificate is awarded to</p>

    <h2>{{ $student->name }}</h2>

    <p>for successfully completing the course</p>

    <h2>{{ $course->title }}</h2>

    <p>Issued on {{ now()->format('Y-m-d') }}</p>
</body>
</html>