<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{$title}}</title>
</head>
<body><h1>File upload example</h1>
<form action="/upload" enctype="multipart/form-data" method="post"><label for="file">Select file to upload：</label>
    <input id="file" name="file" type="file"><br><br> <input type="submit" value="Upload">
</form>
</body>
</html>
