# Layering Architecture Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restructure the architecture into a Shared Kernel + Vertical Domains model by removing horizontal `Interfaces` and `Exceptions` directories and fixing layering violations.

**Architecture:** We will use shell scripts to bulk-migrate interfaces and exceptions into their respective domains, updating all namespace references across the codebase. Then we will update the `LAYER_ORDER` config to resolve the Validator -> Dependency violation and enforce the new layering.

**Tech Stack:** PHP 8.4, Bash (for bulk renaming)

---

### Task 1: Relocate Exceptions into Vertical Domains

**Files:**
- Modify: `packages/core/src/Exceptions/*` -> various domain exception folders

- [ ] **Step 1: Run the exception migration script**

Create a temporary script `migrate_exceptions.sh` and run it:
```bash
cat << 'EOF' > migrate_exceptions.sh
#!/bin/bash
declare -A mapping=(
    ["ExecutionException.php"]="Execution/Exceptions"
    ["GotoTargetNotFoundException.php"]="Execution/Exceptions"
    ["WorkflowCycleException.php"]="Execution/Exceptions"
    ["WorkflowDepthExceededException.php"]="Execution/Exceptions"
    ["StepBudgetExceededException.php"]="Execution/Exceptions"
    ["SchemaValidationException.php"]="Validator/Exceptions"
    ["UnsupportedSerializationStyleException.php"]="Parser/Exceptions"
    ["UnsupportedSourceVersionException.php"]="Resolver/Exceptions"
    ["DefinitionHydrationException.php"]="State/Exceptions"
)

for file in "${!mapping[@]}"; do
    src="packages/core/src/Exceptions/$file"
    dest_dir="packages/core/src/${mapping[$file]}"
    dest_file="$dest_dir/$file"
    
    if [ -f "$src" ]; then
        mkdir -p "$dest_dir"
        mv "$src" "$dest_file"
        
        old_ns="Alama\\\\Arazzo\\\\Exceptions"
        new_ns="Alama\\\\Arazzo\\\\$(echo ${mapping[$file]} | sed 's/\//\\\\/g')"
        
        find packages -type f -name "*.php" -exec sed -i '' "s/$old_ns\\\\${file%.php}/$new_ns\\\\${file%.php}/g" {} +
        sed -i '' "s/namespace $old_ns;/namespace $new_ns;/g" "$dest_file"
    fi
done
EOF
chmod +x migrate_exceptions.sh
./migrate_exceptions.sh
rm migrate_exceptions.sh
```

- [ ] **Step 2: Remove the empty Exceptions directory**
```bash
rm -rf packages/core/src/Exceptions
```

- [ ] **Step 3: Commit**
```bash
git add packages/
git commit -m "refactor: relocate exceptions into vertical domains"
```

### Task 2: Relocate Interfaces into Vertical Domains

**Files:**
- Modify: `packages/core/src/Interfaces/*` -> various domain interface folders

- [ ] **Step 1: Run the interface migration script**

Create a temporary script `migrate_interfaces.sh` and run it:
```bash
cat << 'EOF' > migrate_interfaces.sh
#!/bin/bash
declare -A mapping=(
    ["AiClientInterface.php"]="Execution/Interfaces"
    ["BackoffCalculatorInterface.php"]="Execution/Interfaces"
    ["CriteriaEvaluatorInterface.php"]="Evaluation/Interfaces"
    ["DefinitionRegistryInterface.php"]="State/Interfaces"
    ["EventLedgerInterface.php"]="Events/Interfaces"
    ["ExecutionRegistryInterface.php"]="State/Interfaces"
    ["ExpressionEvaluatorInterface.php"]="Expression/Interfaces"
    ["ExpressionResolverInterface.php"]="Expression/Interfaces"
    ["HttpClientInterface.php"]="Infrastructure/Interfaces"
    ["LockManagerInterface.php"]="State/Interfaces"
    ["LockStrategyInterface.php"]="State/Interfaces"
    ["OpenApiExecutorInterface.php"]="Execution/Interfaces"
    ["OpenApiNormalizerInterface.php"]="Normalizer/Interfaces"
    ["OutputExtractorInterface.php"]="Execution/Interfaces"
    ["PendingCorrelationRegistryInterface.php"]="State/Interfaces"
    ["ProtocolExecutorRegistryInterface.php"]="Execution/Interfaces"
    ["QueueDriverInterface.php"]="Async/Interfaces"
    ["SchemaValidatorInterface.php"]="Validator/Interfaces"
    ["StateStoreInterface.php"]="State/Interfaces"
    ["StepProtocolExecutorInterface.php"]="Protocol/Interfaces"
    ["WritableDefinitionRegistryInterface.php"]="State/Interfaces"
)

for file in "${!mapping[@]}"; do
    src="packages/core/src/Interfaces/$file"
    dest_dir="packages/core/src/${mapping[$file]}"
    dest_file="$dest_dir/$file"
    
    if [ -f "$src" ]; then
        mkdir -p "$dest_dir"
        mv "$src" "$dest_file"
        
        old_ns="Alama\\\\Arazzo\\\\Interfaces"
        new_ns="Alama\\\\Arazzo\\\\$(echo ${mapping[$file]} | sed 's/\//\\\\/g')"
        
        find packages -type f -name "*.php" -exec sed -i '' "s/$old_ns\\\\${file%.php}/$new_ns\\\\${file%.php}/g" {} +
        sed -i '' "s/namespace $old_ns;/namespace $new_ns;/g" "$dest_file"
    fi
done
EOF
chmod +x migrate_interfaces.sh
./migrate_interfaces.sh
rm migrate_interfaces.sh
```

- [ ] **Step 2: Remove the empty Interfaces directory**
```bash
rm -rf packages/core/src/Interfaces
```

- [ ] **Step 3: Commit**
```bash
git add packages/
git commit -m "refactor: relocate interfaces into vertical domains"
```

### Task 3: Fix Layering Order & Dependency Violation

**Files:**
- Modify: `scripts/generate-docs/LayeringDoc.php`

- [ ] **Step 1: Update the LAYER_ORDER array**

Update `scripts/generate-docs/LayeringDoc.php` to drop `Interfaces` and `Exceptions`, and move `Dependency` down the stack (before `Parser` and `Validator`) so that higher domains can depend on it without violating layering. You can use this sed command or edit manually:

```bash
sed -i '' -e "/'Interfaces',/d" -e "/'Exceptions',/d" -e "/'Dependency',/d" scripts/generate-docs/LayeringDoc.php
sed -i '' "/'Parser',/i\\
    'Dependency',
" scripts/generate-docs/LayeringDoc.php
```
Verify `LAYER_ORDER` has `Dependency` above `Parser` (which means it's lower in rank since it's bottom-to-top) and `Interfaces`/`Exceptions` are removed.

- [ ] **Step 2: Regenerate docs to verify layering**

```bash
composer run docs
```

- [ ] **Step 3: Commit**
```bash
git add scripts/generate-docs/LayeringDoc.php docs/
git commit -m "refactor: update layer order and resolve dependency violations"
```

### Task 4: Fix CS, PHPStan, and Tests

**Files:**
- Modify: any files failing PHPStan or tests

- [ ] **Step 1: Format codebase**
```bash
composer format
```

- [ ] **Step 2: Run static analysis**
```bash
composer analyse
```
> Note: The migration scripts handle most namespace replacements, but edge cases in docblocks or aliased imports might need manual cleanup if PHPStan complains. Fix them if needed.

- [ ] **Step 3: Run tests**
```bash
composer test
```

- [ ] **Step 4: Commit fixes**
```bash
git add packages/
git commit -m "chore: fix static analysis and formatting after domain restructuring"
```
