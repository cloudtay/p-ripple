<html>
@foreach($arr as $key => $value)
{{$title}}
@if($key === 'name')
123
@elseif($key === 'age')
456
@else
2789
@endif
@endforeach
</html>
