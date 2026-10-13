import { execFile } from "node:child_process";
import { readFile } from "node:fs/promises";
import { join } from "node:path";
import { fileURLToPath } from "node:url";
import { promisify } from "node:util";
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { z } from "zod";

const PROJECT_ROOT = fileURLToPath(new URL("../../../", import.meta.url));

const execFileAsync = promisify(execFile);

function clipOutput(output: string): string {
    if (output.length <= 30_000) {
        return output;
    }
    return output.slice(0, 12_000) + "\n... output truncated ...\n" + output.slice(-16_000);
}

const server = new McpServer({
    name: "cultivatrace",
    version: "0.7.0",
});

async function runSymfonyCommand(args: string[]): Promise<string> {
    const { stdout, stderr } = await execFileAsync(
        "php",
        ["bin/console", ...args, "--no-interaction"],
        {
            cwd: PROJECT_ROOT,
            windowsHide: true,
            maxBuffer: 10 * 1024 * 1024,
            env: {
                ...process.env,
                APP_ENV: process.env.APP_ENV ?? "test",
                DATABASE_URL:
                    process.env.DATABASE_URL ??
                    "sqlite:///%kernel.project_dir%/data/database.sqlite",
            },
        },
    );

    if (stderr.trim() !== "") {
        throw new Error(stderr.trim());
    }

    return stdout;
}

async function runQualityCommand(binary: string, args: string[]): Promise<{
    exitCode: number;
    output: string;
}> {
    try {
        const { stdout, stderr } = await execFileAsync("php", [binary, ...args], {
            cwd: PROJECT_ROOT,
            windowsHide: true,
            maxBuffer: 4 * 1024 * 1024,
            timeout: 300_000,
            env: {
                ...process.env,
                APP_ENV: "test",
                DATABASE_URL:
                    process.env.DATABASE_URL ??
                    "sqlite:///%kernel.project_dir%/data/database.sqlite",
            },
        });
        return { exitCode: 0, output: clipOutput(stdout + stderr) };
    } catch (error) {
        const failure = error as Error & {
            code?: number;
            stdout?: string;
            stderr?: string;
        };
        return {
            exitCode: typeof failure.code === "number" ? failure.code : 1,
            output: clipOutput((failure.stdout ?? "") + (failure.stderr ?? "") || failure.message),
        };
    }
}

server.registerTool(
    "cultivatrace_project_info",
    {
        description:
            "Returns high-level information about the Cultivatrace project and its architecture.",
    },
    async () => {
        return {
            content: [
                {
                    type: "text",
                    text: JSON.stringify(
                        {
                            project: "Cultivatrace",
                            type: "SaaS multi-tenant",
                            backend: "Symfony 8.1",
                            api: "API Platform 4.2",
                            frontend: "Vue 3 + Quasar",
                            database: "PostgreSQL 16 + TimescaleDB",
                            realtime: "Mercure",
                            authentication: "JWT",
                            architecture: [
                                "DDD",
                                "Legacy Entity model",
                                "Application",
                                "Infrastructure",
                            ],
                            mcp: {
                                version: "0.7.0",
                                mode: "development",
                                access: "inspection and local quality checks",
                            },
                        },
                        null,
                        2,
                    ),
                },
            ],
        };
    },
);

server.registerTool(
    "cultivatrace_audit_questions",
    {
        description:
            "Reads the Cultivatrace AUDIT_QUESTIONS.md file. Use this before making architectural decisions involving legacy entities, the DDD domain model, multi-tenancy, audit trails, security, compliance, Stripe, or other documented architectural questions.",
    },
    async () => {
        const auditQuestionsPath = join(PROJECT_ROOT, "AUDIT_QUESTIONS.md");

        try {
            const content = await readFile(auditQuestionsPath, "utf-8");

            return {
                content: [
                    {
                        type: "text",
                        text: content,
                    },
                ],
            };
        } catch (error) {
            const message =
                error instanceof Error
                    ? error.message
                    : "Unknown error while reading AUDIT_QUESTIONS.md";

            return {
                isError: true,
                content: [
                    {
                        type: "text",
                        text: `Unable to read AUDIT_QUESTIONS.md: ${message}`,
                    },
                ],
            };
        }
    },
);

server.registerTool(
    "cultivatrace_symfony_routes",
    {
        description:
            "Returns the current Symfony routes of Cultivatrace by executing bin/console debug:router. Use this to inspect the real runtime routing configuration instead of inferring routes from source files.",
    },
    async () => {
        try {
            const stdout = await runSymfonyCommand([
                "debug:router",
                "--format=json",
            ]);

            const routes = JSON.parse(stdout);

            return {
                content: [
                    {
                        type: "text",
                        text: JSON.stringify(routes, null, 2),
                    },
                ],
            };
        } catch (error) {
            const message =
                error instanceof Error
                    ? error.message
                    : "Unknown error while reading Symfony routes";

            return {
                isError: true,
                content: [
                    {
                        type: "text",
                        text: `Unable to read Symfony routes: ${message}`,
                    },
                ],
            };
        }
    },
);

server.registerTool(
    "cultivatrace_doctrine_mapping",
    {
        description:
            "Returns the Doctrine entities currently mapped by Cultivatrace. Use this to inspect the real Doctrine mapping instead of inferring mapped entities from source files.",
    },
    async () => {
        try {
            const stdout = await runSymfonyCommand([
                "doctrine:mapping:info",
                "--no-ansi",
            ]);

            const entityPattern = /\[OK\]\s+([^\r\n]+)/g;
            const entities: string[] = [];

            let match: RegExpExecArray | null;

            while ((match = entityPattern.exec(stdout)) !== null) {
                const entity = match[1]?.trim();

                if (entity) {
                    entities.push(entity);
                }
            }

            const result = {
                count: entities.length,
                entities,
            };

            return {
                content: [
                    {
                        type: "text",
                        text: JSON.stringify(result, null, 2),
                    },
                ],
            };
        } catch (error) {
            const message =
                error instanceof Error
                    ? error.message
                    : "Unknown error while reading Doctrine mapping";

            return {
                isError: true,
                content: [
                    {
                        type: "text",
                        text: `Unable to read Doctrine mapping: ${message}`,
                    },
                ],
            };
        }
    },
);

server.registerTool(
    "cultivatrace_doctrine_entity",
    {
        description:
            "Returns detailed Doctrine metadata for one mapped Cultivatrace entity, including table, fields and associations. The entity must be a fully qualified App namespace class.",
        inputSchema: {
            entity: z
                .string()
                .min(1)
                .regex(
                    /^App\\[A-Za-z0-9_\\]+$/,
                    "Entity must be a fully qualified class in the App namespace, for example App\\Entity\\Plant.",
                )
                .describe(
                    "Fully qualified Doctrine entity class, for example App\\Entity\\Plant.",
                ),
        },
    },
    async ({ entity }) => {
        try {
            const stdout = await runSymfonyCommand([
                "doctrine:mapping:describe",
                entity,
                "--no-ansi",
            ]);

            return {
                content: [
                    {
                        type: "text",
                        text: stdout,
                    },
                ],
            };
        } catch (error) {
            const message =
                error instanceof Error
                    ? error.message
                    : "Unknown error while describing Doctrine entity";

            return {
                isError: true,
                content: [
                    {
                        type: "text",
                        text: `Unable to describe Doctrine entity "${entity}": ${message}`,
                    },
                ],
            };
        }
    },
);

server.registerTool(
    "cultivatrace_symfony_workflows",
    {
        description:
            "Returns the active Symfony workflow definitions, places and transitions from the runtime configuration. Does not execute transitions or modify entities.",
        inputSchema: {
            name: z
                .string()
                .regex(/^[A-Za-z][A-Za-z0-9_]*$/)
                .optional()
                .describe("Optional workflow name, for example crop_lifecycle."),
        },
    },
    async ({ name }) => {
        try {
            const stdout = await runSymfonyCommand([
                "debug:config",
                "framework",
                "workflows",
                "--format=json",
            ]);
            const jsonStart = stdout.indexOf("{");
            if (jsonStart < 0) {
                throw new Error("Symfony did not return workflow JSON.");
            }

            type Workflow = {
                type: string;
                supports: string[];
                initial_marking: string[];
                places: Array<{ name: string }>;
                transitions: Record<string, {
                    from: Array<{ place: string }>;
                    to: Array<{ place: string }>;
                }>;
            };
            const config = JSON.parse(stdout.slice(jsonStart)) as {
                workflows: Record<string, Workflow>;
            };
            const available = Object.keys(config.workflows ?? {});
            if (name && !available.includes(name)) {
                return {
                    isError: true,
                    content: [{
                        type: "text" as const,
                        text: `Unknown workflow "${name}". Available: ${available.join(", ")}`,
                    }],
                };
            }

            const selected = name ? [name] : available;
            const workflows = Object.fromEntries(selected.map((workflowName) => {
                const workflow = config.workflows[workflowName]!;
                return [workflowName, {
                    type: workflow.type,
                    supports: workflow.supports,
                    initialMarking: workflow.initial_marking,
                    places: workflow.places.map((place) => place.name),
                    transitions: Object.fromEntries(
                        Object.entries(workflow.transitions).map(([transitionName, transition]) => [
                            transitionName,
                            {
                                from: transition.from.map((place) => place.place),
                                to: transition.to.map((place) => place.place),
                            },
                        ]),
                    ),
                }];
            }));

            return {
                content: [{type: "text" as const, text: JSON.stringify(workflows, null, 2)}],
            };
        } catch (error) {
            return {
                isError: true,
                content: [{
                    type: "text" as const,
                    text: `Unable to read Symfony workflows: ${error instanceof Error ? error.message : String(error)}`,
                }],
            };
        }
    },
);

server.registerTool(
    "cultivatrace_phpunit",
    {
        description:
            "Runs a PHPUnit test file or the full suite locally. May write test fixtures and caches; ask the user before invoking.",
        inputSchema: {
            testFile: z
                .string()
                .regex(/^tests(?:\/[A-Za-z0-9_]+)+Test\.php$/)
                .optional()
                .describe("Optional test file under tests/, for example tests/Service/StripeServiceTest.php."),
        },
    },
    async ({ testFile }) => {
        const result = await runQualityCommand("vendor/bin/phpunit", [
            "--no-coverage",
            ...(testFile ? [testFile] : []),
        ]);
        return {
            isError: result.exitCode !== 0,
            content: [{
                type: "text" as const,
                text: `exit_code=${result.exitCode}\n${result.output}`,
            }],
        };
    },
);

server.registerTool(
    "cultivatrace_phpstan",
    {
        description:
            "Runs PHPStan locally with phpstan.dist.neon and reports its exit code and findings.",
    },
    async () => {
        const result = await runQualityCommand("vendor/bin/phpstan", [
            "analyse",
            "--configuration=phpstan.dist.neon",
            "--no-progress",
            "--memory-limit=1G",
        ]);
        return {
            isError: result.exitCode !== 0,
            content: [{
                type: "text" as const,
                text: `exit_code=${result.exitCode}\n${result.output}`,
            }],
        };
    },
);

const transport = new StdioServerTransport();

await server.connect(transport);
process.stdin.resume();
