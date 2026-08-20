# Domain Glossary

- **Expression Evaluator**: The low-level module that parses and resolves an Arazzo Expression string against the current Workflow Context.
- **Request Compiler**: The module that takes an Arazzo Step and evaluates its inputs to produce a concrete HTTP Request (e.g., PSR-7 `RequestInterface`).
- **Output Extractor**: The module that processes an HTTP Response and extracts values into the Workflow Context based on the Step's output definitions.
- **Criteria Evaluator**: The module that evaluates Success Criteria expressions for a Step to determine if the execution succeeded.
- **Schema Validator**: The module that validates an HTTP Response body against an expected OpenAPI schema.
