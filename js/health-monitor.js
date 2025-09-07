class SystemHealthMonitor {
  constructor() {
    this.api = new StashSmartAPI();
    this.healthData = null;
    this.updateInterval = null;
    this.init();
  }

  init() {
    this.loadHealthData();
    this.startAutoRefresh();
    this.bindEvents();
  }

  async loadHealthData() {
    try {
      const response = await fetch("/backend/api/health.php?action=health");
      this.healthData = await response.json();
      this.renderHealthDashboard();
    } catch (error) {
      console.error("Failed to load health data:", error);
      this.showError("Failed to connect to system health monitoring");
    }
  }

  async loadSystemStats() {
    try {
      const response = await fetch("/backend/api/health.php?action=stats");
      const stats = await response.json();
      this.renderSystemStats(stats);
    } catch (error) {
      console.error("Failed to load system stats:", error);
    }
  }

  renderHealthDashboard() {
    const container = document.getElementById("health-dashboard");
    if (!container) return;

    const statusColor = this.getStatusColor(this.healthData.status);

    container.innerHTML = `
            <div class="health-overview">
                <div class="health-status ${this.healthData.status}">
                    <h2>System Status: <span class="status-badge ${
                      this.healthData.status
                    }">${this.healthData.status.toUpperCase()}</span></h2>
                    <p>Last Updated: ${this.healthData.timestamp}</p>
                </div>
            </div>

            <div class="health-grid">
                ${this.renderDatabaseHealth()}
                ${this.renderApiHealth()}
                ${this.renderPerformanceMetrics()}
                ${this.renderUserActivity()}
                ${this.renderSystemResources()}
                ${this.renderFileSystemHealth()}
            </div>

            <div class="health-actions">
                <button onclick="healthMonitor.refreshHealth()" class="btn btn-primary">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <button onclick="healthMonitor.downloadReport()" class="btn btn-secondary">
                    <i class="fas fa-download"></i> Download Report
                </button>
                <button onclick="healthMonitor.toggleAutoRefresh()" class="btn btn-info">
                    <i class="fas fa-clock"></i> ${
                      this.updateInterval ? "Stop" : "Start"
                    } Auto-Refresh
                </button>
            </div>
        `;
  }

  renderDatabaseHealth() {
    const db = this.healthData.checks.database;
    return `
            <div class="health-card ${db.status}">
                <div class="card-header">
                    <h3><i class="fas fa-database"></i> Database</h3>
                    <span class="status-indicator ${db.status}"></span>
                </div>
                <div class="card-body">
                    <p><strong>Status:</strong> ${db.message}</p>
                    <p><strong>Size:</strong> ${db.database_size_mb || 0} MB</p>
                    ${
                      db.missing_tables && db.missing_tables.length > 0
                        ? `<p class="warning"><strong>Missing Tables:</strong> ${db.missing_tables.join(
                            ", "
                          )}</p>`
                        : ""
                    }
                </div>
            </div>
        `;
  }

  renderApiHealth() {
    const api = this.healthData.checks.api;
    return `
            <div class="health-card ${api.status}">
                <div class="card-header">
                    <h3><i class="fas fa-plug"></i> API Endpoints</h3>
                    <span class="status-indicator ${api.status}"></span>
                </div>
                <div class="card-body">
                    <p><strong>Healthy:</strong> ${api.healthy_endpoints}/${
      api.total_endpoints
    }</p>
                    <div class="endpoint-list">
                        ${Object.entries(api.details)
                          .map(
                            ([endpoint, data]) => `
                            <div class="endpoint-item ${data.status}">
                                <span class="endpoint-name">${endpoint}</span>
                                <span class="response-time">${data.response_time_ms}ms</span>
                            </div>
                        `
                          )
                          .join("")}
                    </div>
                </div>
            </div>
        `;
  }

  renderPerformanceMetrics() {
    const perf = this.healthData.checks.performance;
    return `
            <div class="health-card ${perf.status}">
                <div class="card-header">
                    <h3><i class="fas fa-tachometer-alt"></i> Performance</h3>
                    <span class="status-indicator ${perf.status}"></span>
                </div>
                <div class="card-body">
                    <p><strong>Memory Usage:</strong> ${perf.memory_usage_mb} MB</p>
                    <p><strong>Memory Peak:</strong> ${perf.memory_peak_mb} MB</p>
                    <p><strong>DB Query Time:</strong> ${perf.db_query_time_ms} ms</p>
                    <p><strong>PHP Version:</strong> ${perf.php_version}</p>
                </div>
            </div>
        `;
  }

  renderUserActivity() {
    const activity = this.healthData.checks.user_activity;
    return `
            <div class="health-card ${activity.status}">
                <div class="card-header">
                    <h3><i class="fas fa-users"></i> User Activity</h3>
                    <span class="status-indicator ${activity.status}"></span>
                </div>
                <div class="card-body">
                    <p><strong>Active (24h):</strong> ${
                      activity.active_users_24h || 0
                    }</p>
                    <p><strong>Active (7d):</strong> ${
                      activity.active_users_7d || 0
                    }</p>
                    <p><strong>Total Users:</strong> ${
                      activity.total_users || 0
                    }</p>
                    <p><strong>Transactions (24h):</strong> ${
                      activity.transactions_24h || 0
                    }</p>
                    <p><strong>Engagement Rate:</strong> ${
                      activity.user_engagement_rate || 0
                    }%</p>
                </div>
            </div>
        `;
  }

  renderSystemResources() {
    const resources = this.healthData.checks.resources;
    return `
            <div class="health-card ${resources.status}">
                <div class="card-header">
                    <h3><i class="fas fa-server"></i> System Resources</h3>
                    <span class="status-indicator ${resources.status}"></span>
                </div>
                <div class="card-body">
                    <p><strong>Server Time:</strong> ${resources.server_time}</p>
                    <p><strong>Timezone:</strong> ${resources.timezone}</p>
                    <p><strong>Memory Limit:</strong> ${resources.php_memory_limit}</p>
                    <p><strong>Max Execution:</strong> ${resources.max_execution_time}s</p>
                    <p><strong>Upload Limit:</strong> ${resources.upload_max_filesize}</p>
                </div>
            </div>
        `;
  }

  renderFileSystemHealth() {
    const fs = this.healthData.checks.filesystem;
    return `
            <div class="health-card ${fs.status}">
                <div class="card-header">
                    <h3><i class="fas fa-folder"></i> File System</h3>
                    <span class="status-indicator ${fs.status}"></span>
                </div>
                <div class="card-body">
                    <p><strong>Disk Usage:</strong> ${
                      fs.disk_usage_percent
                    }%</p>
                    <p><strong>Free Space:</strong> ${fs.free_space_mb} MB</p>
                    <div class="directory-list">
                        ${Object.entries(fs.directories)
                          .map(
                            ([dir, data]) => `
                            <div class="directory-item ${
                              data.exists && data.readable ? "ok" : "error"
                            }">
                                <span class="dir-name">${dir}</span>
                                <span class="permissions">
                                    ${data.readable ? "R" : ""}${
                              data.writable ? "W" : ""
                            }
                                </span>
                            </div>
                        `
                          )
                          .join("")}
                    </div>
                </div>
            </div>
        `;
  }

  renderSystemStats(stats) {
    const statsContainer = document.getElementById("system-stats");
    if (!statsContainer || stats.error) return;

    statsContainer.innerHTML = `
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-info">
                        <h3>${stats.users.total_users}</h3>
                        <p>Total Users</p>
                        <small>${
                          stats.users.new_users_30d
                        } new this month</small>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-exchange-alt"></i></div>
                    <div class="stat-info">
                        <h3>${stats.transactions.total_transactions}</h3>
                        <p>Total Transactions</p>
                        <small>${
                          stats.transactions.transactions_30d
                        } this month</small>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                    <div class="stat-info">
                        <h3>$${parseFloat(
                          stats.accounts.total_balance || 0
                        ).toLocaleString()}</h3>
                        <p>Total Balance</p>
                        <small>${stats.accounts.total_accounts} accounts</small>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-bullseye"></i></div>
                    <div class="stat-info">
                        <h3>${stats.goals.total_goals}</h3>
                        <p>Financial Goals</p>
                        <small>${stats.goals.completed_goals} completed</small>
                    </div>
                </div>
            </div>
        `;
  }

  getStatusColor(status) {
    switch (status) {
      case "healthy":
        return "#28a745";
      case "warning":
        return "#ffc107";
      case "error":
        return "#dc3545";
      default:
        return "#6c757d";
    }
  }

  startAutoRefresh() {
    if (this.updateInterval) return;

    this.updateInterval = setInterval(() => {
      this.loadHealthData();
    }, 30000); // Refresh every 30 seconds
  }

  stopAutoRefresh() {
    if (this.updateInterval) {
      clearInterval(this.updateInterval);
      this.updateInterval = null;
    }
  }

  toggleAutoRefresh() {
    if (this.updateInterval) {
      this.stopAutoRefresh();
    } else {
      this.startAutoRefresh();
    }
    this.renderHealthDashboard();
  }

  refreshHealth() {
    const refreshBtn = document.querySelector(".health-actions button");
    if (refreshBtn) {
      refreshBtn.innerHTML =
        '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
      refreshBtn.disabled = true;
    }

    this.loadHealthData().finally(() => {
      if (refreshBtn) {
        refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
        refreshBtn.disabled = false;
      }
    });
  }

  downloadReport() {
    const report = {
      timestamp: new Date().toISOString(),
      system_health: this.healthData,
      generated_by: "StashSmart Health Monitor",
    };

    const blob = new Blob([JSON.stringify(report, null, 2)], {
      type: "application/json",
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `health-report-${new Date().toISOString().slice(0, 10)}.json`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  showError(message) {
    const container = document.getElementById("health-dashboard");
    if (container) {
      container.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    ${message}
                </div>
            `;
    }
  }

  bindEvents() {
    // Add event listeners for real-time updates
    document.addEventListener("visibilitychange", () => {
      if (document.hidden) {
        this.stopAutoRefresh();
      } else {
        this.startAutoRefresh();
      }
    });
  }

  destroy() {
    this.stopAutoRefresh();
  }
}

// Initialize health monitor
let healthMonitor;
document.addEventListener("DOMContentLoaded", function () {
  if (document.getElementById("health-dashboard")) {
    healthMonitor = new SystemHealthMonitor();
  }
});
