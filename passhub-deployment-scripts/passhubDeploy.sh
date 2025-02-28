#!/bin/bash

# Parameters Test:
# Example:
# ./passhubDeploy.sh --azure appId TenantId ClientSecret subdomain.Region username
# ./passhubDeploy.sh --azure 1-2-3-4 5-6-7-8 abc~fgh passhub1.westus mvv

set -e

run_Scripts() {
    for i in 01-packages.sh 02-dirs-files-composer.sh 03-nginx-config.sh; do
        echo "Running $i"
        echo $2
        echo $3
        echo $4
        echo $5
        # Calling each script while passing all arguments
        bash $i $2 $3 $4 $5 $6
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



#if [ "$1" == "--azure" ]; then
##  if [ -z "$1" ]; then
#    echo "Not all Arguments Provided or Null"
#    exit 1
#  else
#    echo "Application Id: " $2
#    echo "Tenant Id: " $3
#    echo "Client Secret: " $4
#    echo "SubDomain and Region: " $5
#  fi
#elif [ "$1" == "--help" ]; then
#  echo "--azure [applicationId] [tenantId] [clientSecret] [Subdomain + Region]"
#else
#  echo "Not using azure"
#fi

