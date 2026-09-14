import Encore from '@symfony/webpack-encore';

Encore
    .setOutputPath('./src/Resources/public/')
    .setPublicPath('/')
    .setManifestKeyPrefix('bundles/protung-easyadmin-plus')

    .cleanupOutputBeforeBuild()
    .enableSourceMaps(false)
    .enableVersioning(false)
    .disableSingleRuntimeChunk()
    .configureCssMinimizerPlugin((options, MinimizerPlugin) => {
        options.minify = MinimizerPlugin.lightningCssMinify;
    })

    .addEntry('app', '/assets/js/app.js')
;

export default Encore.getWebpackConfig();
