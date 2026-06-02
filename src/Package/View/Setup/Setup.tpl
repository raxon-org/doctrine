{{$register = Package.Raxon.Doctrine:Setup:register()}}
{{if(!is.empty($register))}}
{{Package.Raxon.Doctrine:Setup:role.system.import()}}
{{$response = Package.Raxon.Doctrine:Setup:system.config(flags(), options())}}
{{$response}}
{{$response = Package.Raxon.Doctrine:Setup:system.doctrine(flags(), options())}}
{{$response}}
{{$response = Package.Raxon.Doctrine:Setup:system.doctrine.environment(flags(), options())}}
{{$response}}
{{$response = Package.Raxon.Doctrine:Setup:doctrine.bin(flags(), options())}}
{{$response}}
{{$response = Package.Raxon.Doctrine:Setup:schema.update(flags(), options())}}
/*
add doctrine entity create commands here
*/
{{/if}}