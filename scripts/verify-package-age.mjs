import { readFileSync } from 'node:fs';
import { parse } from 'yaml';

// Inspect resolved versions; never substitute the registry latest tag.
const lock = parse(readFileSync(new URL('../pnpm-lock.yaml', import.meta.url), 'utf8'));
const metadata = new Map();
let rejected = 0;
let errors = 0;
for (const key of Object.keys(lock.packages ?? {})) {
    const separator = key.lastIndexOf('@');
    const name = key.slice(0, separator);
    const version = key.slice(separator + 1);
    try {
        if (!metadata.has(name)) {
            const response = await fetch(`https://registry.npmjs.org/${encodeURIComponent(name)}`, {
                signal: AbortSignal.timeout(15000),
            });
            if (!response.ok) throw new Error(`Registro HTTP ${response.status}`);
            metadata.set(name, await response.json());
        }
        const published = metadata.get(name).time?.[version];
        if (!published || !Number.isFinite(Date.parse(published))) throw new Error('Fecha no verificable');
        const days = Math.floor((Date.now() - Date.parse(published)) / 86400000);
        if (days < 30) {
            console.error(`${name}@${version}: ${days} días; requiere revisión por antigüedad.`);
            rejected++;
        }
    } catch (error) {
        console.error(`${name}@${version}: ${error.message}`);
        errors++;
    }
}
if (!Object.keys(lock.packages ?? {}).length) errors++;
console.log(`Antigüedad: ${rejected} versiones recientes, ${errors} errores. No sustituye pnpm audit.`);
process.exitCode = rejected || errors ? 1 : 0;
