TARGET_DIR=/usr/local/share/gini

all: system

system:
	mkdir -p build/system
	deploy/bin/pack system/class build/system/class.phar
	deploy/bin/pack system/view build/system/view.phar

install:
	mkdir -p $TARGET_DIR
	cp -a bin $TARGET_DIR
	cp -a data $TARGET_DIR
	cp -a build/system $TARGET_DIR
