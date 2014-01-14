TARGET_DIR=/usr/local/share/gini

all: system

.FORCE:

system: .FORCE
	@gini-build system .system.build

install:
	@mkdir -p $(TARGET_DIR)
	@cp -r bin lib data $(TARGET_DIR)
	@rm -r $(TARGET_DIR)/system
	@cp -r .system.build $(TARGET_DIR)/system

clean:
	@rm -rf .system.build
