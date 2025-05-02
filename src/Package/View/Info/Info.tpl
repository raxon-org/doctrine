{{$request = request()}}
Package: {{$request.package}}

Module: {{$request.module|string.uppercase.first}}

{{if(!is.empty($request.submodule))}}
Submodule: {{$request.submodule|string.uppercase.first}}
{{/if}}
{{$selected = [
'Database',
'Schema',
'Sequence',
'Table/Column',
'Table/Foreign',
'Table/Index',
'Table',
]}}
{{foreach($selected as $select)}}
{{$files = dir.read(config('controller.dir.view') + $select)}}
{{$files = data.sort($files, ['url' => 'ASC'])}}
{{$files = data.filter($files, ['type' => 'file'])}}
Commands:
{{foreach($files as $file)}}
{{$file.basename = file.basename($file.name, config('extension.tpl'))}}
{{binary()}} {{$request.package}} {{$select|string.lowercase|string.replace:'/':' '}} {{$file.basename|string.lowercase}}

{{/foreach}}
{{/foreach}}