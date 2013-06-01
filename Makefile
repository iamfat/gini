system:
	mkdir -p build
	gini-pack system build/system.phar

install:
	mkdir -p /usr/share/gini
	mkdir -p /usr/share/gini-apps
	cp -a bin /usr/share/gini
	cp -a system /usr/share/gini
