#!/bin/bash
mkdir -p packages/core/src/Execution/Exceptions
mkdir -p packages/core/src/Validator/Exceptions
mkdir -p packages/core/src/Parser/Exceptions
mkdir -p packages/core/src/Resolver/Exceptions
mkdir -p packages/core/src/State/Exceptions

move_exception() {
    file="$1"
    dest_path="$2"
    src="packages/core/src/Exceptions/$file"
    dest_dir="packages/core/src/$dest_path"
    dest_file="$dest_dir/$file"
    
    if [ -f "$src" ]; then
        mv "$src" "$dest_file"
        old_ns="Alama\\\\Arazzo\\\\Exceptions"
        new_ns="Alama\\\\Arazzo\\\\$(echo "$dest_path" | sed 's/\//\\\\/g')"
        
        find packages -type f -name "*.php" -exec sed -i '' "s/$old_ns\\\\${file%.php}/$new_ns\\\\${file%.php}/g" {} +
        sed -i '' "s/namespace $old_ns;/namespace $new_ns;/g" "$dest_file"
    fi
}

move_exception "ExecutionException.php" "Execution/Exceptions"
move_exception "GotoTargetNotFoundException.php" "Execution/Exceptions"
move_exception "WorkflowCycleException.php" "Execution/Exceptions"
move_exception "WorkflowDepthExceededException.php" "Execution/Exceptions"
move_exception "StepBudgetExceededException.php" "Execution/Exceptions"
move_exception "SchemaValidationException.php" "Validator/Exceptions"
move_exception "UnsupportedSerializationStyleException.php" "Parser/Exceptions"
move_exception "UnsupportedSourceVersionException.php" "Resolver/Exceptions"
move_exception "DefinitionHydrationException.php" "State/Exceptions"
