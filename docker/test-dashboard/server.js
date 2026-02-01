const express = require('express');
const cors = require('cors');
const path = require('path');
const mysql = require('mysql2/promise');
const uuid = require('uuid');
const fetch = require('node-fetch');

// UUID v7 generator (time-sortable UUID)
// Uses uuid.v7 if available, otherwise generates a v7-like UUID manually
function uuidv7() {
    if (typeof uuid.v7 === 'function') {
        return uuid.v7();
    }
    // Fallback: Generate UUID v7-like format (timestamp + random)
    const timestamp = Date.now();
    const timestampHex = timestamp.toString(16).padStart(12, '0');
    const randomHex = Array.from({ length: 20 }, () =>
        Math.floor(Math.random() * 16).toString(16)
    ).join('');

    // Format: xxxxxxxx-xxxx-7xxx-yxxx-xxxxxxxxxxxx
    return `${timestampHex.slice(0, 8)}-${timestampHex.slice(8, 12)}-7${randomHex.slice(0, 3)}-${['8', '9', 'a', 'b'][Math.floor(Math.random() * 4)]}${randomHex.slice(3, 6)}-${randomHex.slice(6, 18)}`;
}

const app = express();
const PORT = process.env.PORT || 3000;

// Database configuration
const DB_CONFIG = {
    host: process.env.DB_HOST || 'mysql-test',
    port: parseInt(process.env.DB_PORT || '3306'),
    user: process.env.DB_USERNAME || 'hyperf',
    password: process.env.DB_PASSWORD || 'hyperf',
    database: process.env.DB_DATABASE || 'hyperf_test',
    waitForConnections: true,
    connectionLimit: 10,
};

// API configuration
const API_HOST = process.env.API_HOST || 'hyperf-test';
const API_PORT = process.env.API_PORT || 9501;

let pool = null;

// Brazilian first names
const FIRST_NAMES = [
    'João', 'Maria', 'Pedro', 'Ana', 'Carlos', 'Juliana', 'Lucas', 'Fernanda',
    'Gabriel', 'Camila', 'Rafael', 'Beatriz', 'Bruno', 'Larissa', 'Diego',
    'Amanda', 'Thiago', 'Letícia', 'Felipe', 'Mariana', 'Gustavo', 'Carolina',
    'Rodrigo', 'Isabela', 'Leonardo', 'Gabriela', 'Marcelo', 'Natália', 'André',
    'Patrícia', 'Ricardo', 'Vanessa', 'Eduardo', 'Aline', 'Vinícius', 'Renata'
];

// Brazilian last names
const LAST_NAMES = [
    'Silva', 'Santos', 'Oliveira', 'Souza', 'Rodrigues', 'Ferreira', 'Alves',
    'Pereira', 'Lima', 'Gomes', 'Costa', 'Ribeiro', 'Martins', 'Carvalho',
    'Almeida', 'Lopes', 'Soares', 'Fernandes', 'Vieira', 'Barbosa', 'Rocha',
    'Dias', 'Nascimento', 'Andrade', 'Moreira', 'Nunes', 'Marques', 'Machado'
];

// Email domains
const EMAIL_DOMAINS = ['gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com.br', 'uol.com.br'];

app.use(cors());
app.use(express.json());
app.use(express.static(path.join(__dirname, 'public')));

// Initialize database connection pool
async function initDatabase(retryCount = 0) {
    const maxRetries = 12; // Max 60 seconds (12 * 5 seconds)
    
    try {
        pool = mysql.createPool(DB_CONFIG);
        const conn = await pool.getConnection();
        console.log('✓ Database connected successfully');
        conn.release();
        
        // Only auto-populate after successful connection
        setTimeout(autoPopulateDatabase, 1000);
        return true;
    } catch (error) {
        console.error('✗ Database connection failed:', error.message);
        
        if (retryCount < maxRetries) {
            const delay = 5000;
            console.log(`  Retrying in ${delay / 1000} seconds... (attempt ${retryCount + 1}/${maxRetries})`);
            setTimeout(() => initDatabase(retryCount + 1), delay);
        } else {
            console.error('❌ Failed to connect to database after maximum retries');
        }
        return false;
    }
}

// Generate random Brazilian CPF (11 digits, not validated)
function generateCPF() {
    let cpf = '';
    for (let i = 0; i < 11; i++) {
        cpf += Math.floor(Math.random() * 10);
    }
    return cpf;
}

// Generate random name
function generateName() {
    const firstName = FIRST_NAMES[Math.floor(Math.random() * FIRST_NAMES.length)];
    const lastName = LAST_NAMES[Math.floor(Math.random() * LAST_NAMES.length)];
    return `${firstName} ${lastName}`;
}

// Generate email from name
function generateEmail(name) {
    const domain = EMAIL_DOMAINS[Math.floor(Math.random() * EMAIL_DOMAINS.length)];
    const normalized = name
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/\s+/g, '.')
        + Math.floor(Math.random() * 1000);
    return `${normalized}@${domain}`;
}

// Generate random balance (with various scenarios)
function generateBalance(scenario = 'random') {
    switch (scenario) {
        case 'negative':
            return -(Math.random() * 500 + 1).toFixed(2);
        case 'zero':
            return '0.00';
        case 'low':
            return (Math.random() * 100).toFixed(2);
        case 'medium':
            return (Math.random() * 1000 + 100).toFixed(2);
        case 'high':
            return (Math.random() * 10000 + 1000).toFixed(2);
        case 'random':
        default:
            // 10% negative, 10% zero, 30% low, 30% medium, 20% high
            const rand = Math.random();
            if (rand < 0.1) return generateBalance('negative');
            if (rand < 0.2) return generateBalance('zero');
            if (rand < 0.5) return generateBalance('low');
            if (rand < 0.8) return generateBalance('medium');
            return generateBalance('high');
    }
}

// API Routes

// Get configuration
app.get('/api/config', (req, res) => {
    res.json({
        apiBaseUrl: `http://${API_HOST}:${API_PORT}`,
        apiHost: API_HOST,
        apiPort: API_PORT,
        dbHost: DB_CONFIG.host,
        dbDatabase: DB_CONFIG.database,
        defaultPixKey: process.env.DEFAULT_PIX_KEY || 'withdraw@tecnofit.com'
    });
});

// Health check
app.get('/health', async (req, res) => {
    let dbStatus = 'disconnected';
    try {
        if (pool) {
            await pool.query('SELECT 1');
            dbStatus = 'connected';
        }
    } catch (e) {
        dbStatus = 'error: ' + e.message;
    }
    res.json({ status: 'ok', database: dbStatus });
});

// Setup test data - Creates accounts with various balances
app.post('/api/setup', async (req, res) => {
    const count = parseInt(req.body.count) || 100;
    const includeEdgeCases = req.body.includeEdgeCases !== false;

    try {
        const conn = await pool.getConnection();

        // Truncate tables
        await conn.query('SET FOREIGN_KEY_CHECKS = 0');
        await conn.query('TRUNCATE TABLE account_withdraw_pix');
        await conn.query('TRUNCATE TABLE account_withdraw');
        await conn.query('TRUNCATE TABLE account');
        await conn.query('SET FOREIGN_KEY_CHECKS = 1');

        const accounts = [];

        // Create edge case accounts first (for testing)
        if (includeEdgeCases) {
            const edgeCases = [
                { id: '00000000-0000-0000-0000-000000000001', name: 'Race Test Account 1', balance: '1000.00', locked: false },
                { id: '00000000-0000-0000-0000-000000000002', name: 'Race Test Account 2', balance: '500.00', locked: false },
                { id: '00000000-0000-0000-0000-000000000003', name: 'Low Balance Account', balance: '50.00', locked: false },
                { id: '00000000-0000-0000-0000-000000000004', name: 'Zero Balance Account', balance: '0.00', locked: false },
                { id: '00000000-0000-0000-0000-000000000005', name: 'Negative Balance Account', balance: '-100.00', locked: false },
                { id: '00000000-0000-0000-0000-000000000006', name: 'Locked Account', balance: '1000.00', locked: true },
            ];

            for (let i = 0; i < edgeCases.length; i++) {
                const ec = edgeCases[i];
                const cpf = String(i + 1).padStart(11, '0');

                accounts.push([
                    ec.id, cpf, ec.name, ec.balance, ec.locked ? 1 : 0, ec.locked ? new Date() : null
                ]);
            }
        }

        // Generate random accounts
        const usedCPFs = new Set();
        for (let i = 0; i < count; i++) {
            const id = uuidv7();
            const name = generateName();

            // Generate unique CPF
            let cpf;
            do {
                cpf = generateCPF();
            } while (usedCPFs.has(cpf));
            usedCPFs.add(cpf);

            const balance = generateBalance();
            const locked = Math.random() < 0.05; // 5% locked

            accounts.push([id, cpf, name, balance, locked ? 1 : 0, locked ? new Date() : null]);
        }

        // Batch insert accounts
        if (accounts.length > 0) {
            const batchSize = 1000;
            for (let i = 0; i < accounts.length; i += batchSize) {
                const batch = accounts.slice(i, i + batchSize);
                await conn.query(
                    'INSERT INTO account (id, cpf, name, balance, locked, locked_at) VALUES ?',
                    [batch]
                );
            }
        }

        conn.release();

        res.json({
            success: true,
            message: `Created ${accounts.length} accounts`,
            data: {
                totalAccounts: accounts.length,
                edgeCaseAccounts: includeEdgeCases ? 6 : 0,
                randomAccounts: count
            }
        });
    } catch (error) {
        console.error('Setup error:', error);
        res.status(500).json({
            success: false,
            message: error.message
        });
    }
});

// Truncate all tables (clear database)
app.post('/api/truncate', async (req, res) => {
    try {
        const conn = await pool.getConnection();

        await conn.query('SET FOREIGN_KEY_CHECKS = 0');
        await conn.query('TRUNCATE TABLE account_withdraw_pix');
        await conn.query('TRUNCATE TABLE account_withdraw');
        await conn.query('TRUNCATE TABLE account');
        await conn.query('SET FOREIGN_KEY_CHECKS = 1');

        conn.release();

        res.json({
            success: true,
            message: 'All tables truncated'
        });
    } catch (error) {
        res.status(500).json({
            success: false,
            message: error.message
        });
    }
});

// Reset all accounts to initial state
app.post('/api/reset-all', async (req, res) => {
    try {
        const conn = await pool.getConnection();

        // Reset edge case accounts
        const resets = [
            ['00000000-0000-0000-0000-000000000001', 1000.00, 0],
            ['00000000-0000-0000-0000-000000000002', 500.00, 0],
            ['00000000-0000-0000-0000-000000000003', 50.00, 0],
            ['00000000-0000-0000-0000-000000000004', 0.00, 0],
            ['00000000-0000-0000-0000-000000000005', -100.00, 0],
            ['00000000-0000-0000-0000-000000000006', 1000.00, 1],
        ];

        for (const [id, balance, locked] of resets) {
            await conn.query(
                'UPDATE account SET balance = ?, locked = ?, locked_at = ? WHERE id = ?',
                [balance, locked, locked ? new Date() : null, id]
            );
        }

        // Clear withdrawals
        await conn.query('SET FOREIGN_KEY_CHECKS = 0');
        await conn.query('TRUNCATE TABLE account_withdraw_pix');
        await conn.query('TRUNCATE TABLE account_withdraw');
        await conn.query('SET FOREIGN_KEY_CHECKS = 1');

        conn.release();

        res.json({
            success: true,
            message: 'All accounts reset to initial state'
        });
    } catch (error) {
        res.status(500).json({
            success: false,
            message: error.message
        });
    }
});

// Reset specific account
app.post('/api/reset-account/:accountId', async (req, res) => {
    const { accountId } = req.params;

    const balances = {
        '00000000-0000-0000-0000-000000000001': { balance: 1000.00, locked: false },
        '00000000-0000-0000-0000-000000000002': { balance: 500.00, locked: false },
        '00000000-0000-0000-0000-000000000003': { balance: 50.00, locked: false },
        '00000000-0000-0000-0000-000000000004': { balance: 0.00, locked: false },
        '00000000-0000-0000-0000-000000000005': { balance: -100.00, locked: false },
        '00000000-0000-0000-0000-000000000006': { balance: 1000.00, locked: true },
    };

    try {
        const conn = await pool.getConnection();

        const config = balances[accountId] || { balance: 1000.00, locked: false };

        await conn.query(
            'UPDATE account SET balance = ?, locked = ?, locked_at = ? WHERE id = ?',
            [config.balance, config.locked ? 1 : 0, config.locked ? new Date() : null, accountId]
        );

        conn.release();

        res.json({
            success: true,
            message: 'Account reset',
            data: { accountId, ...config }
        });
    } catch (error) {
        res.status(500).json({
            success: false,
            message: error.message
        });
    }
});

// Get all accounts
app.get('/api/accounts', async (req, res) => {
    const limit = parseInt(req.query.limit) || 100;
    const offset = parseInt(req.query.offset) || 0;

    try {
        const conn = await pool.getConnection();

        const [rows] = await conn.query(
            'SELECT id, cpf, name, balance, locked FROM account ORDER BY id LIMIT ? OFFSET ?',
            [limit, offset]
        );

        const [countResult] = await conn.query('SELECT COUNT(*) as total FROM account');

        conn.release();

        res.json({
            success: true,
            data: rows,
            total: countResult[0].total,
            limit,
            offset
        });
    } catch (error) {
        res.status(500).json({
            success: false,
            message: error.message
        });
    }
});

// Get account statistics
app.get('/api/stats', async (req, res) => {
    try {
        const conn = await pool.getConnection();

        const [stats] = await conn.query(`
            SELECT
                COUNT(*) as totalAccounts,
                SUM(CASE WHEN balance < 0 THEN 1 ELSE 0 END) as negativeBalance,
                SUM(CASE WHEN balance = 0 THEN 1 ELSE 0 END) as zeroBalance,
                SUM(CASE WHEN balance > 0 THEN 1 ELSE 0 END) as positiveBalance,
                SUM(CASE WHEN locked = 1 THEN 1 ELSE 0 END) as lockedAccounts,
                MIN(balance) as minBalance,
                MAX(balance) as maxBalance,
                AVG(balance) as avgBalance
            FROM account
        `);

        const [withdrawStats] = await conn.query(`
            SELECT
                COUNT(*) as totalWithdrawals,
                SUM(CASE WHEN done = 1 AND error = 0 THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN error = 1 THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN scheduled = 1 AND done = 0 THEN 1 ELSE 0 END) as pending
            FROM account_withdraw
        `);

        conn.release();

        res.json({
            success: true,
            data: {
                accounts: stats[0],
                withdrawals: withdrawStats[0]
            }
        });
    } catch (error) {
        res.status(500).json({
            success: false,
            message: error.message
        });
    }
});

// ==================== API Proxy ====================
// Proxy requests to the Hyperf API (browser can't access Docker network directly)

app.post('/api/proxy/withdraw/:accountId', async (req, res) => {
    const { accountId } = req.params;
    
    // Extract API config from request body or use defaults
    const apiConfig = req.body._apiConfig || {};
    const apiHost = apiConfig.host || API_HOST;
    const apiPort = apiConfig.port || API_PORT;
    
    // Remove _apiConfig from the actual API request body
    const { _apiConfig, ...requestBody } = req.body;
    
    const apiUrl = `http://${apiHost}:${apiPort}/account/${accountId}/balance/withdraw`;

    const start = Date.now();

    try {
        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestBody),
            timeout: 30000
        });

        const responseTime = Date.now() - start;
        const data = await response.json().catch(() => ({}));

        res.status(response.status).json({
            ...data,
            _proxy: {
                status: response.status,
                responseTime
            }
        });
    } catch (error) {
        const responseTime = Date.now() - start;
        let errorMessage = error.message;
        
        // Add helpful hint for localhost errors
        if (apiHost === 'localhost' || apiHost === '127.0.0.1') {
            errorMessage += ` | ⚠️ Hint: Use 'hyperf-test:9501' instead of 'localhost' when running inside Docker`;
        }
        
        res.status(500).json({
            success: false,
            message: `Proxy error: ${errorMessage}`,
            _proxy: {
                status: 500,
                responseTime: responseTime,
                error: errorMessage,
                attemptedUrl: apiUrl
            }
        });
    }
});

// Test API connection
app.post('/api/proxy/health', async (req, res) => {
    const apiHost = req.body.host || API_HOST;
    const apiPort = req.body.port || API_PORT;
    
    const apiUrl = `http://${apiHost}:${apiPort}/`;

    try {
        const start = Date.now();
        const response = await fetch(apiUrl, { timeout: 5000 });
        const responseTime = Date.now() - start;

        res.json({
            success: true,
            message: 'API is reachable',
            status: response.status,
            responseTime
        });
    } catch (error) {
        let errorMessage = error.message;
        
        // Add helpful hint for localhost errors
        if (apiHost === 'localhost' || apiHost === '127.0.0.1') {
            errorMessage += ` | ⚠️ Hint: Use 'hyperf-test:9501' instead of 'localhost' when running inside Docker`;
        }
        
        res.json({
            success: false,
            message: `API unreachable: ${errorMessage}`,
            attemptedUrl: apiUrl
        });
    }
});

// Auto-populate database on startup
async function autoPopulateDatabase() {
    const AUTO_POPULATE_COUNT = parseInt(process.env.AUTO_POPULATE_COUNT || '100');

    // Check if pool is ready
    if (!pool) {
        console.log('⏳ Waiting for database connection...');
        setTimeout(autoPopulateDatabase, 2000);
        return;
    }

    try {
        const conn = await pool.getConnection();

        // Check if database is empty
        const [rows] = await conn.query('SELECT COUNT(*) as count FROM account');
        const count = rows[0].count;

        if (count === 0) {
            console.log(`\n📦 Database is empty. Auto-populating with ${AUTO_POPULATE_COUNT} accounts...`);

            const accounts = [];

            // Create edge case accounts first (for testing)
            const edgeCases = [
                { id: '00000000-0000-0000-0000-000000000001', name: 'Race Test Account 1', balance: '1000.00', locked: false },
                { id: '00000000-0000-0000-0000-000000000002', name: 'Race Test Account 2', balance: '500.00', locked: false },
                { id: '00000000-0000-0000-0000-000000000003', name: 'Low Balance Account', balance: '50.00', locked: false },
                { id: '00000000-0000-0000-0000-000000000004', name: 'Zero Balance Account', balance: '0.00', locked: false },
                { id: '00000000-0000-0000-0000-000000000005', name: 'Negative Balance Account', balance: '-100.00', locked: false },
                { id: '00000000-0000-0000-0000-000000000006', name: 'Locked Account', balance: '1000.00', locked: true },
            ];

            for (let i = 0; i < edgeCases.length; i++) {
                const ec = edgeCases[i];
                const cpf = String(i + 1).padStart(11, '0');

                accounts.push([
                    ec.id, cpf, ec.name, ec.balance, ec.locked ? 1 : 0, ec.locked ? new Date() : null
                ]);
            }

            // Generate random accounts
            const usedCPFs = new Set();
            for (let i = 0; i < AUTO_POPULATE_COUNT; i++) {
                const id = uuidv7();
                const name = generateName();

                let cpf;
                do {
                    cpf = generateCPF();
                } while (usedCPFs.has(cpf));
                usedCPFs.add(cpf);

                const balance = generateBalance();
                const locked = Math.random() < 0.05;

                accounts.push([id, cpf, name, balance, locked ? 1 : 0, locked ? new Date() : null]);
            }

            // Batch insert
            const batchSize = 1000;
            for (let i = 0; i < accounts.length; i += batchSize) {
                const batch = accounts.slice(i, i + batchSize);
                await conn.query(
                    'INSERT INTO account (id, cpf, name, balance, locked, locked_at) VALUES ?',
                    [batch]
                );
            }

            console.log(`✅ Created ${accounts.length} accounts (${edgeCases.length} edge cases + ${AUTO_POPULATE_COUNT} random)`);
            console.log('');
            console.log('📋 Edge Case Test Accounts:');
            console.log('   ID: 00000000-0000-0000-0000-000000000001 | R$ 1000.00 | Active');
            console.log('   ID: 00000000-0000-0000-0000-000000000002 | R$  500.00 | Active');
            console.log('   ID: 00000000-0000-0000-0000-000000000003 | R$   50.00 | Active');
            console.log('   ID: 00000000-0000-0000-0000-000000000004 | R$    0.00 | Active');
            console.log('   ID: 00000000-0000-0000-0000-000000000005 | R$ -100.00 | Active');
            console.log('   ID: 00000000-0000-0000-0000-000000000006 | R$ 1000.00 | LOCKED');
            console.log('');
        } else {
            console.log(`\n✓ Database already has ${count} accounts`);
        }

        conn.release();
    } catch (error) {
        console.error('❌ Auto-populate failed:', error.message);
        console.log('   Retrying in 5 seconds...');
        setTimeout(autoPopulateDatabase, 5000);
    }
}

// Start server
app.listen(PORT, '0.0.0.0', async () => {
    console.log(`\n=== TecnoFit Test Dashboard ===`);
    console.log(`Server: http://0.0.0.0:${PORT}`);
    console.log(`API Target: http://${API_HOST}:${API_PORT}`);
    console.log(`Database: ${DB_CONFIG.host}:${DB_CONFIG.port}/${DB_CONFIG.database}`);
    console.log('');

    await initDatabase();
});
