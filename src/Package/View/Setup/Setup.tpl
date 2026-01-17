{{$register = Package.Raxon.Doctrine:Setup:register()}}
{{if(!is.empty($register))}}
{{Package.Raxon.Doctrine:Setup:role.system.import()}}
{{$response = Package.Raxon.Doctrine:Setup:system.config(flags(), options())}}
{{$response = Package.Raxon.Doctrine:Setup:system.doctrine(flags(), options())}}
{{$response = Package.Raxon.Doctrine:Setup:system.doctrine.environment(flags(), options())}}
{{$response = Package.Raxon.Doctrine:Setup:doctrine.bin(flags(), options())}}
{{/if}}