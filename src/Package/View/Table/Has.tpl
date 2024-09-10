{{R3M}}
{{$response = Package.Raxon.Doctrine:Main:table.has(flags(), options())}}
{{$response|json.encode:'JSON_PRETTY_PRINT'}}

