var Encore = require('@symfony/webpack-encore');

Encore
    .setOutputPath('./src/Resources/public/')
    .setPublicPath('/')
    .setManifestKeyPrefix('bundles/protung-easyadmin-plus')

    .cleanupOutputBeforeBuild()
    .enableSourceMaps(false)
    .enableVersioning(false)
    .disableSingleRuntimeChunk()

    .addEntry('app', '/assets/js/app.js')
;

module.exports = Encore.getWebpackConfig();
