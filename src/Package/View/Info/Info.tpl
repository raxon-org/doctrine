{{$request = request()}}
Package: {{$request.package}}

Module: {{$request.module|string.uppercase.first}}

{{if(!is.empty($request.submodule))}}
Submodule: {{$request.submodule|string.uppercase.first}}
{{/if}}
{{if($request.module === 'info')}}
{{$files = dir.read(config('controller.dir.view'))}}
{{$files = data.sort($files, ['url' => 'ASC'])}}
{{dd($files)}}
Commands:
{{foreach($files as $file)}}
{{$file.basename = file.basename($file.name, config('extension.tpl'))}}
{{binary()}} {{$request.package}} object {{$file.basename|string.lowercase}}

{{/foreach}}
{{/if}}