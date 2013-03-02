#!bash
#
# gini-completion
# ===================
# 
# Bash completion support for [gini](http://geneegroup.com/giniphp)

_gini() 
{
	COMPREPLY=()

	local cur

	cur=$(_get_cword)

	case "$cur" in
	@) 
		COMPREPLY=($( compgen -W "$(gini apps)" -- "$cur" )) 
		;;
	*)   
		COMPREPLY=( $( compgen -W "$(gini ?)" -- "$cur" ) )
		;;
	esac

	return 0
} &&
complete -F _gini gini
