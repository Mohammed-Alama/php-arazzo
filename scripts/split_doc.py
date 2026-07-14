#!/usr/bin/env python3
import os
import sys
import re

def split_markdown_by_tasks(input_file_path, output_dir="split_tasks"):
    """
    Splits a large markdown file into individual .md files based on 
    lines starting with '### Task'.
    """
    if not os.path.exists(output_dir):
        os.makedirs(output_dir)
        print(f"Created directory: {output_dir}")

    current_file = None
    task_count = 0

    try:
        with open(input_file_path, 'r', encoding='utf-8') as infile:
            for line in infile:
                if line.strip().startswith('### Task'):
                    if current_file:
                        current_file.close()
                    
                    task_count += 1
                    
                    # Clean up '### Task 2' -> 'Task-2'
                    task_title = line.strip().replace('#', '').strip()
                    safe_filename = re.sub(r'[^\w\-_\. ]', '', task_title).replace(' ', '-') + ".md"
                    
                    output_path = os.path.join(output_dir, safe_filename)
                    print(f"Creating: {output_path}")
                    
                    current_file = open(output_path, 'w', encoding='utf-8')
                    current_file.write(line)
                else:
                    if current_file:
                        current_file.write(line)
                        
            if current_file:
                current_file.close()
                
        print(f"\nSuccess! Systematically split {task_count} markdown files into '{output_dir}/'.")

    except FileNotFoundError:
        print(f"Error: The file '{input_file_path}' was not found.")
        sys.exit(1)

if __name__ == "__main__":
    # If you pass a filename in the shell, use it. Otherwise, default to 'tasks.md'
    if len(sys.argv) > 1:
        target_file = sys.argv[1]
    else:
        target_file = 'tasks.md'
        
    split_markdown_by_tasks(target_file)