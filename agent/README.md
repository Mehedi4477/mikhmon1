# MikhMon Agent Panel

This is the agent/reseller panel for the MikhMon system. Agents can use this panel to:
- Generate vouchers using their balance
- View their current balance
- Check transaction history
- View all generated vouchers

## Setup

1. Make sure the agent system database is installed (run `install_agent_system.php` if not already done)
2. Create agent accounts through the admin panel (MikhMon → Agent/Reseller → Tambah Agent)
3. Set prices for each profile through the admin panel (MikhMon → Agent/Reseller → Kelola Harga)

## Login

Agents can login using their phone number and password at:
`http://your-domain/mikhmon/agent/`

## Features

### Dashboard
- View current balance
- Generate vouchers with profile selection
- View recent transaction history

### My Vouchers
- View all generated vouchers
- See voucher status (active, used, expired)

### Transaction History
- Complete transaction history
- View balance changes over time

## API Integration

The agent panel uses the same API as the admin system for voucher generation:
`/mikhmon/api/agent_generate_voucher.php`

All voucher generation is logged in the database with automatic balance deduction.