{{R3M}}
{{$response = Package.Raxon.Doctrine:Main:table.index.all(flags(), options())}}
{{$response|json.encode:'JSON_PRETTY_PRINT'}}

