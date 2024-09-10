{{R3M}}
{{$response = Package.Raxon.Doctrine:Main:table.foreign.keys(flags(), options())}}
{{$response|json.encode:'JSON_PRETTY_PRINT'}}

