const defaultConfig = require("@wordpress/scripts/config/webpack.config");
const path = require("path");
const isProduction = "production" === process.env.NODE_ENV;
const {entryPoints} = require("./pluginMachine.json");

let entry = {};
if( entryPoints.hasOwnProperty("blocks") ){
    entryPoints.blocks.forEach(
        (entryPoint) => {
            entry[`block-${entryPoint}`] = path.resolve(process.cwd(), `blocks/${entryPoint}/index.js`);
        }
    );
}

if( entryPoints.hasOwnProperty("adminPages") ){
    entryPoints.adminPages.forEach(
        (entryPoint) => {
            entry[`admin-page-${entryPoint}`] = path.resolve(process.cwd(), `admin/${entryPoint}/index.js`);
        }
    );
}


module.exports = {
	mode: isProduction ? "production" : "development",
	...defaultConfig,
	module: {
		...defaultConfig.module,
		rules: [
			...defaultConfig.module.rules,
			{
				test: /\.css$/,
				use: ["style-loader", "css-loader"],
			},
		],
	},
	entry,
	output: {
		filename: "[name].js",
		path: path.join(__dirname, "./build"),
	},
	resolve: {
		fallback: {
		  fs: require.resolve('browserify-fs'),
		  stream: require.resolve('stream-browserify'),
		  path: require.resolve('path-browserify')
		}
	  }	  
};
