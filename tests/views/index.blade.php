<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>File upload form example</title></head>
<body><h1>File upload example</h1>
<form action="/" enctype="multipart/form-data" method="post"><label for="file">Select file to upload：</label>
    <input id="file" name="file" type="file"><br><br> <input type="submit" value="Upload">
</form>
<form action="/" method="POST">
    <input name="name" type="text" value="test"> <input name="age" type="text" value="18">
    <input type="submit" value="提交">
</form>
</body>
</html>
