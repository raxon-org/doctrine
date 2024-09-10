{{R3M}}
{{$response = Package.Raxon.Doctrine:Main:sequence.all(flags(), options())}}
{{$response|json.encode:'JSON_PRETTY_PRINT'}}

