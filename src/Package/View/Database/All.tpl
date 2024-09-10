{{R3M}}
{{$response = Package.Raxon.Doctrine:Main:database.all(flags(), options())}}
{{$response|json.encode:'JSON_PRETTY_PRINT'}}

