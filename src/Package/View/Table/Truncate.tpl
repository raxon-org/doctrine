{{R3M}}
{{$response = Package.Raxon.Doctrine:Main:table.truncate(flags(), options())}}
{{$response|json.encode:'JSON_PRETTY_PRINT'}}

