v0.3.7 - 2nd Jun 2016
         Add reporting of allowed origins.
         Add reporting of failed header HeaderNotAllowed.
         Tweak logs to prepend with "CORs:" and remove mis-formatted additional parameter.
         Change phpunit.xml to use beStrictAboutCoversAnnotation  instead of checkForUnintentionallyCoveredCode 
v0.3.6 - 20th Apr 2016
         Add handling of origin port to reply if sent.
v0.3.5 - 19th Apr 2016
         Add handling of origin protocol scheme to reply if sent.
v0.3.0 - 13th Apr 2016
         Added handling of origins which are fully qualified ( such as http://example.com/ instead of just hostname)
v0.2.0 - 5th Jan 2016
         Renamed from Bairwell/Cors to Bairwell/MiddlewareCors (packagist name Bairwell\Middleware-Cors)
         Remove Slim dependency from dev (moved to examples) (fixes https://github.com/bairwell/middleware-cors/issues/2 )
         Added code of conduct and installation instructions (fixed https://github.com/bairwell/middleware-cors/issues/3 )
v0.1.1 - 31st Dec 2015
         Made Preflight and ValidateSettings (formerly Validate) their own classes instead of being traits.
         Migrated from Bitbucket to Github.
v0.1.0 - 30th Dec 2015
         First release.
