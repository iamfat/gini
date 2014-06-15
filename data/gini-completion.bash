#!/bin/bash

_gini() 
{
	COMPREPLY=()

	local cur

	cur=$(_get_cword)

	unset COMP_WORDS[0]

	case "$cur" in
	@) 
		COMPREPLY=($( compgen -W "$(gini -- ${COMP_WORDS[@]})" -- "$cur" )) 
		;;
	*)   
		COMPREPLY=( $( compgen -W "$(gini -- ${COMP_WORDS[@]})" -- "$cur" ) )
		;;
	esac

	return 0
} &&
complete -F _gini gini
