const { PurgeCSS } = require('purgecss');
const path = require('path');
const fs = require('fs');
const CleanCSS = require('clean-css'); // Importation de la bibliothèque de minification

module.exports = {
  content: [
    "./templates/**/*.html.twig", 
    "./assets/**/*.js",
    './src/Form/*.php'
  ],
  css: [
    "./public/assets/css/style.css", 
    "./public/assets/css/slick.min.css", 
    "./public/assets/css/magnific-popup.min.css", 
    "./public/assets/css/fontawesome.min.css", 
    "./public/assets/css/bootstrap.min.css"
  ],
  output: path.resolve(__dirname, 'public/css/'), // Chemin de sortie absolu
  safelist: [],
  rejected: true,

  runPurgeCSS: async function () {
    try {
      const purgeCSSResults = await new PurgeCSS().purge({
        content: this.content,
        css: this.css,
        safelist: this.safelist,
        rejected: this.rejected,
      });

      purgeCSSResults.forEach(result => {
        const outputFileName = path.basename(result.file, '.css') + '.min.css'; // Génère un nom de fichier avec .min.css
        const outputPath = path.join(this.output, outputFileName); // Crée le chemin final correctement

        // Vérification si le répertoire de sortie existe, sinon le créer
        if (!fs.existsSync(this.output)) {
          fs.mkdirSync(this.output, { recursive: true });
        }

        // Minification du CSS purgé avec CleanCSS
        const minifiedCSS = new CleanCSS({}).minify(result.css).styles;

        // Écriture du fichier CSS minifié
        fs.writeFileSync(outputPath, minifiedCSS, 'utf8');
        console.log(`CSS purged and minified written to: ${outputPath}`);
      });
    } catch (error) {
      console.error('Error running PurgeCSS:', error);
    }
  }
};

module.exports.runPurgeCSS();
