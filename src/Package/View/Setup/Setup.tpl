{{R3M}}
{{$register = Package.Raxon.Doctrine:Init:register()}}
{{if(!is.empty($register))}}
{{Package.Raxon.Doctrine:Import:role.system()}}
{{$options = options()}}
{{Package.Raxon.Doctrine:Main:system.config($options)}}
{{Package.Raxon.Doctrine:Main:bin.doctrine($options)}}
{{/if}}