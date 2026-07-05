#!/usr/bin/env node
/**
 * Memento MCP entrypoint — resolves npx-installed package and runs stdio server.
 * Use with system Node (see ~/.cursor/mcp.json), not Cursor's bundled Node 22.
 */
import { existsSync, readdirSync } from "node:fs";
import { homedir } from "node:os";
import { join } from "node:path";
import { pathToFileURL } from "node:url";

function findMementoDist() {
  const localCli = join(import.meta.dirname, "memento-mcp/node_modules/@luispmonteiro/memento-memory-mcp/dist/cli/main.js");
  if (existsSync(localCli)) {
    return localCli;
  }

  const npxRoot = join(homedir(), "AppData/Local/npm-cache/_npx");
  if (!existsSync(npxRoot)) {
    throw new Error(
      "Memento package missing. Run: npm install @luispmonteiro/memento-memory-mcp@latest --prefix tools/memento-mcp --no-save",
    );
  }

  let newest = null;
  for (const dir of readdirSync(npxRoot)) {
    const cli = join(npxRoot, dir, "node_modules/@luispmonteiro/memento-memory-mcp/dist/cli/main.js");
    if (existsSync(cli)) {
      newest = cli;
    }
  }

  if (!newest) {
    throw new Error(
      "Memento package missing. Run: npm install @luispmonteiro/memento-memory-mcp@latest --prefix tools/memento-mcp --no-save",
    );
  }

  return newest;
}

const entry = findMementoDist();
await import(pathToFileURL(entry).href);
