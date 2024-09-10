{{R3M}}
{{$response = Package.Raxon.Doctrine:Main:view.all(flags(), options())}}
{{$response|json.encode:'JSON_PRETTY_PRINT'}}

