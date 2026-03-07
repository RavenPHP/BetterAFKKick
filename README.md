# BetterAFKKick

BetterAFKKick is a powerful and configurable AFK management plugin for PocketMine-MP API 5.0.0 servers.  
It automatically detects inactive players and can kick them, teleport them to an AFK world, or transfer them to AFK servers.

This plugin helps keep your server active by preventing AFK players from occupying player slots.

Developed by DevRaven.

---

## Features

- Automatic AFK detection
- ActionBar countdown warning
- Kick AFK players
- Teleport AFK players to a specific world
- Transfer AFK players to AFK servers
- Multi AFK server support
- Waterdog / Proxy compatible
- Anti fake movement detection
- Manual AFK toggle command
- AFK player list command
- Staff bypass permission
- Highly configurable

---

## Commands

| Command | Description |
|--------|-------------|
| /afk | Toggle AFK mode |
| /afk list | Show all AFK players |

---

## Permissions

| Permission | Description | Default |
|-----------|-------------|--------|
| betterafkkick.command | Allows use of AFK commands | true |
| betterafkkick.bypass | Bypass AFK detection | op |

---

## Configuration

Example config.yml

```yaml
afk-time: 180
countdown: 10
movement-threshold: 0.15

mode: "transfer"

kick:
  message: "§cYou were kicked for being AFK."

teleport:
  world: "afk_world"

transfer:
  servers:
    - "127.0.0.1:19133"
    - "127.0.0.1:19134"

messages:
  actionbar: "§eYou will be moved in {seconds}s"
  afk-on: "§7You are now AFK."
  afk-off: "§aYou are no longer AFK."
  afk-list-header: "§6AFK Players:"
  no-afk: "§aNo players are AFK."
```

---

## How It Works

1. The plugin monitors player movement and activity.
2. If a player is inactive for the configured time, a countdown warning appears in the ActionBar.
3. When the countdown finishes, the configured action will occur:

- Kick the player
- Teleport the player to an AFK world
- Transfer the player to an AFK server

---

## Example Use Cases

- Prevent AFK players from occupying server slots
- Send idle players to a separate AFK server
- Move AFK players to a dedicated AFK world
- Improve server performance

---

## Compatibility

- PocketMine-MP API 5.0.0
- Supports Bedrock Edition
- Compatible with Waterdog / Proxy setups

---

## Installation

1. Download the plugin.
2. Place the plugin folder in your server plugins directory.
3. Start or restart your server.
4. Edit config.yml if needed.

---

## Author

Plugin created by DevRaven.
