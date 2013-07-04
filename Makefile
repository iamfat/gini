TARGET_DIR=/usr/local/share/gini

all: system

.FORCE:

system: .FORCE
	@mkdir -p build/system
	@gini-pack system/class build/system/class.phar
	@gini-pack system/view build/system/view.phar
	@cp -r system/raw build/system
	@cp system/gini.json build/system

install:
	@mkdir -p $(TARGET_DIR)
	@cp -r bin data $(TARGET_DIR)
	@cp -r build/system $(TARGET_DIR)
