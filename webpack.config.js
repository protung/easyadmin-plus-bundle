import Encore from '@symfony/webpack-encore';

Encore
    .setOutputPath('./public/')
    .setPublicPath('/bundles/protungeasyadminplus')
    .setManifestKeyPrefix('bundles/protungeasyadminplus')

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
