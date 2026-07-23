# Strict Runtime Schema Validation Todo

- [x] Task 1: `SchemaValidator` — type, enum, nullable
- [x] Task 2: `SchemaValidator` — required/properties (object) and items (array) recursion
- [x] Task 3: `SchemaValidator` — `pattern` and `format`
- [x] Task 4: `SchemaValidator` — numeric bounds
- [x] Task 5: `SchemaValidator` — length/collection bounds
- [x] Task 6: `SchemaValidator` — `allOf`/`oneOf`/`anyOf` composition
- [x] Task 7: `SchemaValidationException`
- [x] Task 8: `ExpressionResolverInterface::validateResponseSchema()` + `ArazzoExpressionResolver` implementation
- [x] Task 9: Config flag + `Step::$strictValidation` + `x-strict-validation` parsing
- [x] Task 10: Wire validation into `StepExecutor` (sync path)
- [x] Task 11: Wire validation into `HttpStepExecutor` (async path)
- [x] Task 12: `SuccessCriterion` version field + rejection rule
- [x] Task 13: `SuccessCriteriaVersionSupportedRule` — reject unsupported `xpath` version pinsrule
