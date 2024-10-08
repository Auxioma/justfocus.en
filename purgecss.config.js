const { PurgeCSS } = require('purgecss'); // Import PurgeCSS

const path = require('path');
const fs = require('fs');

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
        const outputFileName = path.basename(result.file);
        const outputPath = path.join(this.output, outputFileName); // Crée le chemin final correctement

        // Vérification si le répertoire de sortie existe, sinon le créer
        if (!fs.existsSync(this.output)) {
          fs.mkdirSync(this.output, { recursive: true });
        }

        // Écriture des fichiers CSS purgés
        fs.writeFileSync(outputPath, result.css, 'utf8');
        console.log(`CSS purged and written to: ${outputPath}`);
      });
    } catch (error) {
      console.error('Error running PurgeCSS:', error);
    }
  }
};

// Pour exécuter PurgeCSS avec cette configuration, appelle la fonction runPurgeCSS :
module.exports.runPurgeCSS();
