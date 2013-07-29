TARGET_DIR=/usr/local/share/gini

all: system

.FORCE:

system: .FORCE
	@gini-build system

install:
	@mkdir -p $(TARGET_DIR)
	@cp -r bin data $(TARGET_DIR)
	@cp -r system.build $(TARGET_DIR)/system
