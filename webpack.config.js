const path = require("path");
const glob = require("glob");
const defaultConfig = require("./node_modules/@wordpress/scripts/config/webpack.config");

module.exports = {
  ...defaultConfig,
  entry: glob("./js/blocks/**/index.js", { sync: true }),
  output: {
    filename: "blocks.js",
    chunkFilename: "blocks.js",
    path: path.resolve(__dirname, "js/build")
  }
};
