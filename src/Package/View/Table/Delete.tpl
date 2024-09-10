{{R3M}}
{{$response = Package.Raxon.Doctrine:Main:table.delete(flags(), options())}}
{{$response|json.encode:'JSON_PRETTY_PRINT'}}

