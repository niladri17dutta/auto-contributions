// ----------------------------------------------------------------------------
// LEARNING OBJECTIVE:
// This tutorial will guide you through creating a custom ESLint rule in TypeScript
// to enforce a specific coding style within your project. We'll focus on creating
// a rule that ensures all exported functions have a JSDoc comment. This will help
// you understand the fundamental concepts of ESLint rule creation, including
// traversing the Abstract Syntax Tree (AST) and reporting errors.
// ----------------------------------------------------------------------------

// First, let's define the structure of our ESLint rule.
// ESLint rules are typically defined as objects with several properties.

/**
 * @typedef {object} ESLintRuleContext
 * @property {function(string): void} report - A function to report a problem.
 * @property {object} getSourceCode - A function to get the source code object.
 * // ... other context properties you might need
 */

/**
 * @typedef {object} ESLintRule
 * @property {string} create - A function that returns an object defining the AST nodes to visit.
 * // ... other rule properties
 */

// The 'create' function is where the core logic of our rule resides.
// It receives a 'context' object, which is essential for reporting errors
// and accessing information about the code.

/**
 * @param {ESLintRuleContext} context - The ESLint rule context.
 * @returns {object} An object mapping AST node types to visitor functions.
 */
function create(context) {
  // This is where we'll define our visitor functions.
  // ESLint uses the ESTree specification for representing code as an AST.
  // We'll use 'Program' to visit the entire file, and specifically look for 'ExportNamedDeclaration'.

  return {
    // 'ExportNamedDeclaration' is an AST node that represents a named export.
    // For example: 'export const myVar = 1;' or 'export function myFunction() {}'
    ExportNamedDeclaration(node) {
      // When ESLint encounters an 'ExportNamedDeclaration', this function will be called.

      // We are interested in exports that have a 'declaration'.
      // For example, 'export function myFunction() {}' has a 'functionDeclaration' as its 'declaration'.
      // 'export const myVar = 1;' has a 'variableDeclaration' as its 'declaration'.
      if (node.declaration) {
        // Check if the declaration is a function declaration.
        // We could expand this to include other types like classes, but for this example, we'll focus on functions.
        if (node.declaration.type === 'FunctionDeclaration') {
          // Now, we need to check if this function has a JSDoc comment.
          // JSDoc comments are stored in the 'leadingComments' property of the AST node.

          const hasJSDoc = node.leadingComments && node.leadingComments.some(comment =>
            comment.type === 'Block' && // JSDoc comments are typically block comments (/* ... */)
            comment.value.trim().startsWith('*') // The first character after the opening '/*' should be '*' for JSDoc
          );

          // If the function declaration does NOT have a JSDoc comment, we should report an error.
          if (!hasJSDoc) {
            // The 'report' function is our way of telling ESLint about a problem.
            context.report({
              // 'node': The AST node where the error occurred.
              node: node,
              // 'message': A human-readable message explaining the error.
              message: 'Exported function must have a JSDoc comment.'
            });
          }
        }
      }
    }
  };
}

// Finally, we export our rule. ESLint will load this rule and execute the 'create' function.
// The 'meta' property can contain information about the rule, like its type and schema.
// For simplicity, we'll keep it minimal here.

export default {
  meta: {
    type: 'problem', // 'problem', 'suggestion', or 'layout'
    docs: {
      description: 'Enforce JSDoc comments on exported functions.',
      category: 'Stylistic Issues',
      recommended: false, // Set to true if this rule should be in the recommended config
    },
    fixable: null, // 'code', 'whitespace', or null
    schema: [], // Rule schema for configuration options
  },
  create: create,
};

// ----------------------------------------------------------------------------
// EXAMPLE USAGE (in your .eslintrc.js or .eslintrc.json):
//
// 1. Place this code in a file, e.g., 'eslint-rules/require-exported-function-jsdoc.ts'
//
// 2. In your ESLint configuration file (.eslintrc.js):
//
// module.exports = {
//   // ... other ESLint configurations
//   plugins: [
//     // You might need to configure the path to your custom rules.
//     // This is a simplified example. A more robust setup might involve
//     // publishing your rules as an npm package or using a monorepo setup.
//     '@eslint/eslint-plugin-custom', // If you publish your rules
//   ],
//   rules: {
//     // 'custom/require-exported-function-jsdoc': 'error', // If using a plugin
//     // Or, if you're loading rules directly (less common for complex setups):
//     'require-exported-function-jsdoc': require('./eslint-rules/require-exported-function-jsdoc.ts'),
//   },
// };
//
// 3. Create a TypeScript file with some code to test:
//
// // --- File: src/myModule.ts ---
//
// export function usefulFunction() {
//   // This function is exported but lacks a JSDoc.
//   console.log('Doing something useful!');
// }
//
// /**
//  * This function has a JSDoc.
//  * @param {string} name - The name to greet.
//  */
// export function greet(name: string) {
//   console.log(`Hello, ${name}!`);
// }
//
// const internalVariable = 'secret';
//
// export const exportedValue = 42; // This rule currently only checks functions.
//
// // --- Run ESLint ---
// // npx eslint src/myModule.ts
//
// // You should see an error like:
// // src/myModule.ts:3:8: Exported function must have a JSDoc comment. (custom/require-exported-function-jsdoc)
// ----------------------------------------------------------------------------