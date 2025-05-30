<?php

// autoload-classmap.php @generated by Strauss

$strauss_src = dirname(__FILE__);

return array(
   'MemberPress\Lcobucci\JWT\UnencryptedToken' => $strauss_src . '/lcobucci/jwt/src/UnencryptedToken.php',
   'MemberPress\Lcobucci\JWT\SodiumBase64Polyfill' => $strauss_src . '/lcobucci/jwt/src/SodiumBase64Polyfill.php',
   'MemberPress\Lcobucci\JWT\Token\UnsupportedHeaderFound' => $strauss_src . '/lcobucci/jwt/src/Token/UnsupportedHeaderFound.php',
   'MemberPress\Lcobucci\JWT\Token\InvalidTokenStructure' => $strauss_src . '/lcobucci/jwt/src/Token/InvalidTokenStructure.php',
   'MemberPress\Lcobucci\JWT\Token\Plain' => $strauss_src . '/lcobucci/jwt/src/Token/Plain.php',
   'MemberPress\Lcobucci\JWT\Token\RegisteredClaimGiven' => $strauss_src . '/lcobucci/jwt/src/Token/RegisteredClaimGiven.php',
   'MemberPress\Lcobucci\JWT\Token\Parser' => $strauss_src . '/lcobucci/jwt/src/Token/Parser.php',
   'MemberPress\Lcobucci\JWT\Token\Signature' => $strauss_src . '/lcobucci/jwt/src/Token/Signature.php',
   'MemberPress\Lcobucci\JWT\Token\DataSet' => $strauss_src . '/lcobucci/jwt/src/Token/DataSet.php',
   'MemberPress\Lcobucci\JWT\Token\Builder' => $strauss_src . '/lcobucci/jwt/src/Token/Builder.php',
   'MemberPress\Lcobucci\JWT\Token\RegisteredClaims' => $strauss_src . '/lcobucci/jwt/src/Token/RegisteredClaims.php',
   'MemberPress\Lcobucci\JWT\Validation\Constraint' => $strauss_src . '/lcobucci/jwt/src/Validation/Constraint.php',
   'MemberPress\Lcobucci\JWT\Validation\SignedWith' => $strauss_src . '/lcobucci/jwt/src/Validation/SignedWith.php',
   'MemberPress\Lcobucci\JWT\Validation\NoConstraintsGiven' => $strauss_src . '/lcobucci/jwt/src/Validation/NoConstraintsGiven.php',
   'MemberPress\Lcobucci\JWT\Validation\ConstraintViolation' => $strauss_src . '/lcobucci/jwt/src/Validation/ConstraintViolation.php',
   'MemberPress\Lcobucci\JWT\Validation\Constraint\PermittedFor' => $strauss_src . '/lcobucci/jwt/src/Validation/Constraint/PermittedFor.php',
   'MemberPress\Lcobucci\JWT\Validation\Constraint\SignedWith' => $strauss_src . '/lcobucci/jwt/src/Validation/Constraint/SignedWith.php',
   'MemberPress\Lcobucci\JWT\Validation\Constraint\HasClaimWithValue' => $strauss_src . '/lcobucci/jwt/src/Validation/Constraint/HasClaimWithValue.php',
   'MemberPress\Lcobucci\JWT\Validation\Constraint\LeewayCannotBeNegative' => $strauss_src . '/lcobucci/jwt/src/Validation/Constraint/LeewayCannotBeNegative.php',
   'MemberPress\Lcobucci\JWT\Validation\Constraint\RelatedTo' => $strauss_src . '/lcobucci/jwt/src/Validation/Constraint/RelatedTo.php',
   'MemberPress\Lcobucci\JWT\Validation\Constraint\CannotValidateARegisteredClaim' => $strauss_src . '/lcobucci/jwt/src/Validation/Constraint/CannotValidateARegisteredClaim.php',
   'MemberPress\Lcobucci\JWT\Validation\Constraint\IdentifiedBy' => $strauss_src . '/lcobucci/jwt/src/Validation/Constraint/IdentifiedBy.php',
   'MemberPress\Lcobucci\JWT\Validation\Constraint\ValidAt' => $strauss_src . '/lcobucci/jwt/src/Validation/Constraint/ValidAt.php',
   'MemberPress\Lcobucci\JWT\Validation\Constraint\IssuedBy' => $strauss_src . '/lcobucci/jwt/src/Validation/Constraint/IssuedBy.php',
   'MemberPress\Lcobucci\JWT\Validation\Constraint\StrictValidAt' => $strauss_src . '/lcobucci/jwt/src/Validation/Constraint/StrictValidAt.php',
   'MemberPress\Lcobucci\JWT\Validation\Constraint\LooseValidAt' => $strauss_src . '/lcobucci/jwt/src/Validation/Constraint/LooseValidAt.php',
   'MemberPress\Lcobucci\JWT\Validation\Validator' => $strauss_src . '/lcobucci/jwt/src/Validation/Validator.php',
   'MemberPress\Lcobucci\JWT\Validation\RequiredConstraintsViolated' => $strauss_src . '/lcobucci/jwt/src/Validation/RequiredConstraintsViolated.php',
   'MemberPress\Lcobucci\JWT\Validation\ValidAt' => $strauss_src . '/lcobucci/jwt/src/Validation/ValidAt.php',
   'MemberPress\Lcobucci\JWT\JwtFacade' => $strauss_src . '/lcobucci/jwt/src/JwtFacade.php',
   'MemberPress\Lcobucci\JWT\Encoding\UnifyAudience' => $strauss_src . '/lcobucci/jwt/src/Encoding/UnifyAudience.php',
   'MemberPress\Lcobucci\JWT\Encoding\JoseEncoder' => $strauss_src . '/lcobucci/jwt/src/Encoding/JoseEncoder.php',
   'MemberPress\Lcobucci\JWT\Encoding\ChainedFormatter' => $strauss_src . '/lcobucci/jwt/src/Encoding/ChainedFormatter.php',
   'MemberPress\Lcobucci\JWT\Encoding\MicrosecondBasedDateConversion' => $strauss_src . '/lcobucci/jwt/src/Encoding/MicrosecondBasedDateConversion.php',
   'MemberPress\Lcobucci\JWT\Encoding\UnixTimestampDates' => $strauss_src . '/lcobucci/jwt/src/Encoding/UnixTimestampDates.php',
   'MemberPress\Lcobucci\JWT\Encoding\CannotEncodeContent' => $strauss_src . '/lcobucci/jwt/src/Encoding/CannotEncodeContent.php',
   'MemberPress\Lcobucci\JWT\Encoding\CannotDecodeContent' => $strauss_src . '/lcobucci/jwt/src/Encoding/CannotDecodeContent.php',
   'MemberPress\Lcobucci\JWT\Exception' => $strauss_src . '/lcobucci/jwt/src/Exception.php',
   'MemberPress\Lcobucci\JWT\Encoder' => $strauss_src . '/lcobucci/jwt/src/Encoder.php',
   'MemberPress\Lcobucci\JWT\Parser' => $strauss_src . '/lcobucci/jwt/src/Parser.php',
   'MemberPress\Lcobucci\JWT\Signer\None' => $strauss_src . '/lcobucci/jwt/src/Signer/None.php',
   'MemberPress\Lcobucci\JWT\Signer\UnsafeEcdsa' => $strauss_src . '/lcobucci/jwt/src/Signer/UnsafeEcdsa.php',
   'MemberPress\Lcobucci\JWT\Signer\OpenSSL' => $strauss_src . '/lcobucci/jwt/src/Signer/OpenSSL.php',
   'MemberPress\Lcobucci\JWT\Signer\Key\FileCouldNotBeRead' => $strauss_src . '/lcobucci/jwt/src/Signer/Key/FileCouldNotBeRead.php',
   'MemberPress\Lcobucci\JWT\Signer\Key\LocalFileReference' => $strauss_src . '/lcobucci/jwt/src/Signer/Key/LocalFileReference.php',
   'MemberPress\Lcobucci\JWT\Signer\Key\InMemory' => $strauss_src . '/lcobucci/jwt/src/Signer/Key/InMemory.php',
   'MemberPress\Lcobucci\JWT\Signer\Ecdsa' => $strauss_src . '/lcobucci/jwt/src/Signer/Ecdsa.php',
   'MemberPress\Lcobucci\JWT\Signer\Ecdsa\ConversionFailed' => $strauss_src . '/lcobucci/jwt/src/Signer/Ecdsa/ConversionFailed.php',
   'MemberPress\Lcobucci\JWT\Signer\Ecdsa\Sha256' => $strauss_src . '/lcobucci/jwt/src/Signer/Ecdsa/Sha256.php',
   'MemberPress\Lcobucci\JWT\Signer\Ecdsa\UnsafeSha384' => $strauss_src . '/lcobucci/jwt/src/Signer/Ecdsa/UnsafeSha384.php',
   'MemberPress\Lcobucci\JWT\Signer\Ecdsa\Sha512' => $strauss_src . '/lcobucci/jwt/src/Signer/Ecdsa/Sha512.php',
   'MemberPress\Lcobucci\JWT\Signer\Ecdsa\Sha384' => $strauss_src . '/lcobucci/jwt/src/Signer/Ecdsa/Sha384.php',
   'MemberPress\Lcobucci\JWT\Signer\Ecdsa\MultibyteStringConverter' => $strauss_src . '/lcobucci/jwt/src/Signer/Ecdsa/MultibyteStringConverter.php',
   'MemberPress\Lcobucci\JWT\Signer\Ecdsa\SignatureConverter' => $strauss_src . '/lcobucci/jwt/src/Signer/Ecdsa/SignatureConverter.php',
   'MemberPress\Lcobucci\JWT\Signer\Ecdsa\UnsafeSha512' => $strauss_src . '/lcobucci/jwt/src/Signer/Ecdsa/UnsafeSha512.php',
   'MemberPress\Lcobucci\JWT\Signer\Ecdsa\UnsafeSha256' => $strauss_src . '/lcobucci/jwt/src/Signer/Ecdsa/UnsafeSha256.php',
   'MemberPress\Lcobucci\JWT\Signer\Hmac\Sha256' => $strauss_src . '/lcobucci/jwt/src/Signer/Hmac/Sha256.php',
   'MemberPress\Lcobucci\JWT\Signer\Hmac\UnsafeSha384' => $strauss_src . '/lcobucci/jwt/src/Signer/Hmac/UnsafeSha384.php',
   'MemberPress\Lcobucci\JWT\Signer\Hmac\Sha512' => $strauss_src . '/lcobucci/jwt/src/Signer/Hmac/Sha512.php',
   'MemberPress\Lcobucci\JWT\Signer\Hmac\Sha384' => $strauss_src . '/lcobucci/jwt/src/Signer/Hmac/Sha384.php',
   'MemberPress\Lcobucci\JWT\Signer\Hmac\UnsafeSha512' => $strauss_src . '/lcobucci/jwt/src/Signer/Hmac/UnsafeSha512.php',
   'MemberPress\Lcobucci\JWT\Signer\Hmac\UnsafeSha256' => $strauss_src . '/lcobucci/jwt/src/Signer/Hmac/UnsafeSha256.php',
   'MemberPress\Lcobucci\JWT\Signer\Rsa\Sha256' => $strauss_src . '/lcobucci/jwt/src/Signer/Rsa/Sha256.php',
   'MemberPress\Lcobucci\JWT\Signer\Rsa\UnsafeSha384' => $strauss_src . '/lcobucci/jwt/src/Signer/Rsa/UnsafeSha384.php',
   'MemberPress\Lcobucci\JWT\Signer\Rsa\Sha512' => $strauss_src . '/lcobucci/jwt/src/Signer/Rsa/Sha512.php',
   'MemberPress\Lcobucci\JWT\Signer\Rsa\Sha384' => $strauss_src . '/lcobucci/jwt/src/Signer/Rsa/Sha384.php',
   'MemberPress\Lcobucci\JWT\Signer\Rsa\UnsafeSha512' => $strauss_src . '/lcobucci/jwt/src/Signer/Rsa/UnsafeSha512.php',
   'MemberPress\Lcobucci\JWT\Signer\Rsa\UnsafeSha256' => $strauss_src . '/lcobucci/jwt/src/Signer/Rsa/UnsafeSha256.php',
   'MemberPress\Lcobucci\JWT\Signer\InvalidKeyProvided' => $strauss_src . '/lcobucci/jwt/src/Signer/InvalidKeyProvided.php',
   'MemberPress\Lcobucci\JWT\Signer\Blake2b' => $strauss_src . '/lcobucci/jwt/src/Signer/Blake2b.php',
   'MemberPress\Lcobucci\JWT\Signer\Key' => $strauss_src . '/lcobucci/jwt/src/Signer/Key.php',
   'MemberPress\Lcobucci\JWT\Signer\UnsafeRsa' => $strauss_src . '/lcobucci/jwt/src/Signer/UnsafeRsa.php',
   'MemberPress\Lcobucci\JWT\Signer\CannotSignPayload' => $strauss_src . '/lcobucci/jwt/src/Signer/CannotSignPayload.php',
   'MemberPress\Lcobucci\JWT\Signer\Hmac' => $strauss_src . '/lcobucci/jwt/src/Signer/Hmac.php',
   'MemberPress\Lcobucci\JWT\Signer\Eddsa' => $strauss_src . '/lcobucci/jwt/src/Signer/Eddsa.php',
   'MemberPress\Lcobucci\JWT\Signer\Rsa' => $strauss_src . '/lcobucci/jwt/src/Signer/Rsa.php',
   'MemberPress\Lcobucci\JWT\Validator' => $strauss_src . '/lcobucci/jwt/src/Validator.php',
   'MemberPress\Lcobucci\JWT\Decoder' => $strauss_src . '/lcobucci/jwt/src/Decoder.php',
   'MemberPress\Lcobucci\JWT\Configuration' => $strauss_src . '/lcobucci/jwt/src/Configuration.php',
   'MemberPress\Lcobucci\JWT\Signer' => $strauss_src . '/lcobucci/jwt/src/Signer.php',
   'MemberPress\Lcobucci\JWT\ClaimsFormatter' => $strauss_src . '/lcobucci/jwt/src/ClaimsFormatter.php',
   'MemberPress\Lcobucci\JWT\Builder' => $strauss_src . '/lcobucci/jwt/src/Builder.php',
   'MemberPress\Lcobucci\JWT\Token' => $strauss_src . '/lcobucci/jwt/src/Token.php',
   'MemberPress\Lcobucci\Clock\FrozenClock' => $strauss_src . '/lcobucci/clock/src/FrozenClock.php',
   'MemberPress\Lcobucci\Clock\Clock' => $strauss_src . '/lcobucci/clock/src/Clock.php',
   'MemberPress\Lcobucci\Clock\SystemClock' => $strauss_src . '/lcobucci/clock/src/SystemClock.php',
   'MemberPress\Psr\Container\ContainerExceptionInterface' => $strauss_src . '/psr/container/src/ContainerExceptionInterface.php',
   'MemberPress\Psr\Container\NotFoundExceptionInterface' => $strauss_src . '/psr/container/src/NotFoundExceptionInterface.php',
   'MemberPress\Psr\Container\ContainerInterface' => $strauss_src . '/psr/container/src/ContainerInterface.php',
   'MemberPress\GroundLevel\Container\Service' => $strauss_src . '/caseproof/ground-level-container/Service.php',
   'MemberPress\GroundLevel\Container\Container' => $strauss_src . '/caseproof/ground-level-container/Container.php',
   'MemberPress\GroundLevel\Container\Contracts\StaticContainerAwareness' => $strauss_src . '/caseproof/ground-level-container/Contracts/StaticContainerAwareness.php',
   'MemberPress\GroundLevel\Container\Contracts\ConfiguresParameters' => $strauss_src . '/caseproof/ground-level-container/Contracts/ConfiguresParameters.php',
   'MemberPress\GroundLevel\Container\Contracts\LoadableDependency' => $strauss_src . '/caseproof/ground-level-container/Contracts/LoadableDependency.php',
   'MemberPress\GroundLevel\Container\Contracts\ContainerAwareness' => $strauss_src . '/caseproof/ground-level-container/Contracts/ContainerAwareness.php',
   'MemberPress\GroundLevel\Container\Exception' => $strauss_src . '/caseproof/ground-level-container/Exception.php',
   'MemberPress\GroundLevel\Container\Concerns\HasStaticContainer' => $strauss_src . '/caseproof/ground-level-container/Concerns/HasStaticContainer.php',
   'MemberPress\GroundLevel\Container\Concerns\Configurable' => $strauss_src . '/caseproof/ground-level-container/Concerns/Configurable.php',
   'MemberPress\GroundLevel\Container\Concerns\HasContainer' => $strauss_src . '/caseproof/ground-level-container/Concerns/HasContainer.php',
   'MemberPress\GroundLevel\Container\NotFoundException' => $strauss_src . '/caseproof/ground-level-container/NotFoundException.php',
   'MemberPress\GroundLevel\InProductNotifications\Service' => $strauss_src . '/caseproof/ground-level-in-product-notifications/Service.php',
   'MemberPress\GroundLevel\InProductNotifications\Services\Ajax' => $strauss_src . '/caseproof/ground-level-in-product-notifications/Services/Ajax.php',
   'MemberPress\GroundLevel\InProductNotifications\Services\Store' => $strauss_src . '/caseproof/ground-level-in-product-notifications/Services/Store.php',
   'MemberPress\GroundLevel\InProductNotifications\Services\ScheduledService' => $strauss_src . '/caseproof/ground-level-in-product-notifications/Services/ScheduledService.php',
   'MemberPress\GroundLevel\InProductNotifications\Services\View' => $strauss_src . '/caseproof/ground-level-in-product-notifications/Services/View.php',
   'MemberPress\GroundLevel\InProductNotifications\Services\Retriever' => $strauss_src . '/caseproof/ground-level-in-product-notifications/Services/Retriever.php',
   'MemberPress\GroundLevel\InProductNotifications\Services\Cleaner' => $strauss_src . '/caseproof/ground-level-in-product-notifications/Services/Cleaner.php',
   'MemberPress\GroundLevel\InProductNotifications\Models\Notification' => $strauss_src . '/caseproof/ground-level-in-product-notifications/Models/Notification.php',
   'MemberPress\Caseproof\GrowthTools\Config' => $strauss_src . '/caseproof/growth-tools/src/Config.php',
   'MemberPress\Caseproof\GrowthTools\Helper\AddonInstallSkin' => $strauss_src . '/caseproof/growth-tools/src/Helper/AddonInstallSkin.php',
   'MemberPress\Caseproof\GrowthTools\Helper\AddonHelper' => $strauss_src . '/caseproof/growth-tools/src/Helper/AddonHelper.php',
   'MemberPress\Caseproof\GrowthTools\App' => $strauss_src . '/caseproof/growth-tools/src/App.php',
   'MemberPress\GroundLevel\Support\Str' => $strauss_src . '/caseproof/ground-level-support/Str.php',
   'MemberPress\GroundLevel\Support\Time' => $strauss_src . '/caseproof/ground-level-support/Time.php',
   'MemberPress\GroundLevel\Support\Contracts\Arrayable' => $strauss_src . '/caseproof/ground-level-support/Contracts/Arrayable.php',
   'MemberPress\GroundLevel\Support\Contracts\Jsonable' => $strauss_src . '/caseproof/ground-level-support/Contracts/Jsonable.php',
   'MemberPress\GroundLevel\Support\Casts' => $strauss_src . '/caseproof/ground-level-support/Casts.php',
   'MemberPress\GroundLevel\Support\Concerns\HasUserRelationship' => $strauss_src . '/caseproof/ground-level-support/Concerns/HasUserRelationship.php',
   'MemberPress\GroundLevel\Support\Concerns\HasAttributes' => $strauss_src . '/caseproof/ground-level-support/Concerns/HasAttributes.php',
   'MemberPress\GroundLevel\Support\Concerns\Factory' => $strauss_src . '/caseproof/ground-level-support/Concerns/Factory.php',
   'MemberPress\GroundLevel\Support\Concerns\Macroable' => $strauss_src . '/caseproof/ground-level-support/Concerns/Macroable.php',
   'MemberPress\GroundLevel\Support\Concerns\Hookable' => $strauss_src . '/caseproof/ground-level-support/Concerns/Hookable.php',
   'MemberPress\GroundLevel\Support\Concerns\Serializable' => $strauss_src . '/caseproof/ground-level-support/Concerns/Serializable.php',
   'MemberPress\GroundLevel\Support\Exceptions\Exception' => $strauss_src . '/caseproof/ground-level-support/Exceptions/Exception.php',
   'MemberPress\GroundLevel\Support\Exceptions\TimeTravelError' => $strauss_src . '/caseproof/ground-level-support/Exceptions/TimeTravelError.php',
   'MemberPress\GroundLevel\Support\Exceptions\ReadOnlyAttributeError' => $strauss_src . '/caseproof/ground-level-support/Exceptions/ReadOnlyAttributeError.php',
   'MemberPress\GroundLevel\Support\Enum' => $strauss_src . '/caseproof/ground-level-support/Enum.php',
   'MemberPress\GroundLevel\Support\Models\Model' => $strauss_src . '/caseproof/ground-level-support/Models/Model.php',
   'MemberPress\GroundLevel\Support\Models\User' => $strauss_src . '/caseproof/ground-level-support/Models/User.php',
   'MemberPress\GroundLevel\Support\Models\Hook' => $strauss_src . '/caseproof/ground-level-support/Models/Hook.php',
   'MemberPress\GroundLevel\Support\Util' => $strauss_src . '/caseproof/ground-level-support/Util.php',
   'MemberPress\GroundLevel\Mothership\Credentials' => $strauss_src . '/caseproof/ground-level-mothership/Credentials.php',
   'MemberPress\GroundLevel\Mothership\Manager\AddonInstallSkin' => $strauss_src . '/caseproof/ground-level-mothership/Manager/AddonInstallSkin.php',
   'MemberPress\GroundLevel\Mothership\Manager\LicenseManager' => $strauss_src . '/caseproof/ground-level-mothership/Manager/LicenseManager.php',
   'MemberPress\GroundLevel\Mothership\Manager\AddonsManager' => $strauss_src . '/caseproof/ground-level-mothership/Manager/AddonsManager.php',
   'MemberPress\GroundLevel\Mothership\Service' => $strauss_src . '/caseproof/ground-level-mothership/Service.php',
   'MemberPress\GroundLevel\Mothership\AbstractPluginConnection' => $strauss_src . '/caseproof/ground-level-mothership/AbstractPluginConnection.php',
   'MemberPress\GroundLevel\Mothership\Api\Response' => $strauss_src . '/caseproof/ground-level-mothership/Api/Response.php',
   'MemberPress\GroundLevel\Mothership\Api\Request' => $strauss_src . '/caseproof/ground-level-mothership/Api/Request.php',
   'MemberPress\GroundLevel\Mothership\Api\PaginatedResponse' => $strauss_src . '/caseproof/ground-level-mothership/Api/PaginatedResponse.php',
   'MemberPress\GroundLevel\Mothership\Api\Request\Products' => $strauss_src . '/caseproof/ground-level-mothership/Api/Request/Products.php',
   'MemberPress\GroundLevel\Mothership\Api\Request\LicenseActivations' => $strauss_src . '/caseproof/ground-level-mothership/Api/Request/LicenseActivations.php',
   'MemberPress\GroundLevel\Mothership\Api\Request\Licenses' => $strauss_src . '/caseproof/ground-level-mothership/Api/Request/Licenses.php',
   'MemberPress\GroundLevel\Mothership\Api\Request\Users' => $strauss_src . '/caseproof/ground-level-mothership/Api/Request/Users.php',
);