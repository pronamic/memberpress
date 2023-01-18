const path = require("path");
const glob = require("glob");
const defaultConfig = require("./node_modules/@wordpress/scripts/config/webpack.config");

// Remove defaultConfig sass loader config so we can add ours below
let moduleRules = defaultConfig.module.rules.filter(rule => {
  return !rule.test.toString().includes('(sc|sa)ss')
});

module.exports = {
  ...defaultConfig,
  entry: glob("./js/blocks/**/index.js", { sync: true }),
  output: {
    filename: "blocks.js",
    path: path.resolve(__dirname, "js/build")
  },
  module: {
    ...defaultConfig.module,
    rules: [
      ...moduleRules,
      {
        test: /\.s[ac]ss$/i,
        use: [
          // Creates `style` nodes from JS strings
          "style-loader",
          // Translates CSS into CommonJS
          "css-loader",
          // Compiles Sass to CSS
          "sass-loader",
        ],
      },
      {
        test: /\.svg$/,
        use: ["@svgr/webpack", "url-loader"]
      },
    ]
  }
};
