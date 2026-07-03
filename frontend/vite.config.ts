import { defineConfig } from "vite";
import react from "@vitejs/plugin-react-swc";
import path from "path";

// https://vitejs.dev/config/
export default defineConfig(async ({ mode }) => {
  const plugins: Array<any> = [react()];

  if (mode === "development") {
    try {
      const mod = await import("lovable-tagger");
      if (mod && typeof mod.componentTagger === "function") {
        plugins.push(mod.componentTagger());
      }
    } catch (err) {
      // If the optional dev-only plugin isn't available, warn but continue.
      // This avoids failing production builds when the package expects esbuild.
      // eslint-disable-next-line no-console
      console.warn("lovable-tagger not loaded:", err);
    }
  }

  return {
    server: {
      host: "::",
      port: 8080,
      hmr: {
        overlay: false,
      },
      proxy: {
        "/api": {
          target: "http://localhost:8000",
          changeOrigin: true,
          configure: (proxy) => {
            proxy.on("proxyReq", (proxyReq) => {
              proxyReq.setHeader("Accept-Encoding", "identity");
            });
          },
        },
      },
    },
    plugins,
    build: {
      // Increase limit slightly and provide manual chunking to reduce oversized bundles
      chunkSizeWarningLimit: 700,
      rollupOptions: {
        output: {
          manualChunks(id: string) {
            if (!id) return undefined;
            if (!id.includes('node_modules')) return undefined;

            // Derive package name from path under node_modules
            const parts = id.split('node_modules/').pop()?.split('/');
            if (!parts || parts.length === 0) return undefined;
            let pkg = parts[0];
            // Scoped packages: @scope/name
            if (pkg.startsWith('@') && parts.length > 1) pkg = `${pkg}/${parts[1]}`;

            // Group major frameworks together for better caching
            const reactPkgs = ['react', 'react-dom', 'scheduler', 'react-router', 'react-router-dom'];
            const uiScopes = ['@radix-ui', '@headlessui', '@stitches', '@tremor'];
            const utilsPkgs = ['lodash', 'lodash-es', 'date-fns', 'dayjs'];

            if (reactPkgs.includes(pkg)) return 'react-vendors';
            if (utilsPkgs.includes(pkg)) return 'utils';
            if (uiScopes.some(s => pkg.startsWith(s))) return 'ui-vendors';

            // Default: create a chunk per package to avoid circular chunking
            // e.g. node_modules/axios -> vendor_axios, node_modules/@foo/bar -> vendor_@foo_bar
            const safeName = pkg.replace(/[^a-zA-Z0-9_\-]/g, '_');
            return `vendor_${safeName}`;
          },
        },
      },
    },
    resolve: {
      alias: {
        "@": path.resolve(__dirname, "./src"),
      },
    },
  };
});
