/**
 * Vite resolves `.json` imports to their parsed default export. TypeScript is
 * not configured with `resolveJsonModule`, so declare the shape here and cast
 * at the import site.
 */
declare module '*.json' {
    const value: unknown;

    export default value;
}
