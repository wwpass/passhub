#!/bin/bash

# Parameters Test:
# Example:
# ./passhubDeploy.sh --azure appId TenantId ClientSecret subdomain.Region username
# ./passhubDeploy.sh --azure 1-2-3-4 5-6-7-8 abc~fgh passhub.eastus admin

set -e

workingDirectory=$(dirname $0)

run_Scripts() {
    for i in 01-packages.sh 02-dirs-files-composer.sh 03-nginx-config.sh; do
        echo "Running $workingDirectory/$i"
        # Calling each script while passing all arguments
        bash $workingDirectory/$i $2 $3 $4 $5 $6
    done
}

if [ -n "$1" ]; then
    # if first argument is --azure
    if [ "$1" == "--azure" ]; then
        # check if other 5 inputs are not null
        if [[ -n "$2" ]] && [[ -n "$3" ]] && [[ -n "$4" ]] && [[ -n "$5" ]] && [[ -n "$6" ]]; then
            run_Scripts $1 $2 $3 $4 $5 $6
        else
            echo "One or more Elements Are Empty Or Incorrect"
            exit 1
        fi 
    else
        echo "--azure not provided"
        exit 1 
    fi                                                                                    
else
    echo "No Arguments Provided"
    exit 1
fi
