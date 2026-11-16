import 'package:flutter/material.dart';

import '../../services/analytics_service.dart';
import 'ai_recommendations_screen.dart';
import 'analytics_reports_screen.dart';
import 'data_collection_status_screen.dart';

class AnalyticsDashboardScreen extends StatefulWidget {
  const AnalyticsDashboardScreen({super.key});

  @override
  State<AnalyticsDashboardScreen> createState() =>
      _AnalyticsDashboardScreenState();
}

class _AnalyticsDashboardScreenState extends State<AnalyticsDashboardScreen> {
  final AnalyticsService _service = AnalyticsService();
  Map<String, dynamic>? _overview;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await _service.getOverview();
      setState(() {
        _overview = data;
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final brandCount = int.tryParse(_overview?['brand_count']?.toString() ?? '0') ?? 0;
    final connectedAccounts = int.tryParse(_overview?['connected_accounts_count']?.toString() ?? '0') ?? 0;
    final hasData = _overview?['has_data'] == true;
    final metrics = _overview?['data']?['metrics'] as Map<String, dynamic>?;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Analytics Studio'),
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: _load),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.analytics_outlined,
                            size: 56, color: Colors.grey),
                        const SizedBox(height: 16),
                        Text(
                          _error!.contains('Not authenticated')
                              ? 'Please log in to view analytics.'
                              : 'Could not load analytics. Please try again.',
                          textAlign: TextAlign.center,
                          style: const TextStyle(color: Colors.grey),
                        ),
                        const SizedBox(height: 16),
                        FilledButton(
                            onPressed: _load, child: const Text('Retry')),
                      ],
                    ),
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      // Overview card
                      Card(
                        child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text(
                                'Overview',
                                style: TextStyle(
                                    fontSize: 18, fontWeight: FontWeight.bold),
                              ),
                              const SizedBox(height: 12),
                              _infoRow('Period',
                                  _overview?['period']?.toString() ?? '30d'),
                              _infoRow('Brands tracked', brandCount.toString()),
                              _infoRow('Connected accounts', connectedAccounts.toString()),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 12),

                      // Empty state / diagnostic card
                      if (brandCount == 0)
                        Card(
                          color: const Color(0xFF1E2238),
                          child: Padding(
                            padding: const EdgeInsets.all(16),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Icon(Icons.business_outlined, color: Colors.amber, size: 28),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: const [
                                      Text(
                                        'No Brands Configured',
                                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                                      ),
                                      SizedBox(height: 4),
                                      Text(
                                        'You have not registered any brands in your workspace yet. Create a brand in the Brands section to begin tracking performance and social metrics.',
                                        style: TextStyle(color: Colors.grey, fontSize: 13),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          ),
                        )
                      else if (connectedAccounts == 0)
                        Card(
                          color: const Color(0xFF1E2238),
                          child: Padding(
                            padding: const EdgeInsets.all(16),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Icon(Icons.link_off_outlined, color: Colors.orange, size: 28),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      const Text(
                                        'No Social Accounts Connected',
                                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                                      ),
                                      const SizedBox(height: 4),
                                      Text(
                                        'You have $brandCount brand(s), but no social accounts have been linked yet. Connect Facebook, Instagram, TikTok, YouTube, or LinkedIn accounts in Brand Settings to enable analytics collection.',
                                        style: const TextStyle(color: Colors.grey, fontSize: 13),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          ),
                        )
                      else if (!hasData)
                        Card(
                          color: const Color(0xFF1E2238),
                          child: Padding(
                            padding: const EdgeInsets.all(16),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Icon(Icons.sync_outlined, color: Colors.cyan, size: 28),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: const [
                                      Text(
                                        'Awaiting Analytics Collection',
                                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                                      ),
                                      SizedBox(height: 4),
                                      Text(
                                        'Social accounts are connected, but metrics have not been collected yet. Collection runs automatically via scheduled background workers or can be triggered via Data Collection Status.',
                                        style: TextStyle(color: Colors.grey, fontSize: 13),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          ),
                        )
                      else if (metrics != null)
                        Card(
                          child: Padding(
                            padding: const EdgeInsets.all(16),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text(
                                  'Performance Metrics',
                                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                                ),
                                const SizedBox(height: 12),
                                _infoRow('Total Followers', metrics['total_followers']?.toString() ?? '0'),
                                _infoRow('Total Impressions', metrics['total_impressions']?.toString() ?? '0'),
                                _infoRow('Total Reach', metrics['total_reach']?.toString() ?? '0'),
                                _infoRow('Total Engagement', metrics['total_engagement']?.toString() ?? '0'),
                                _infoRow('Engagement Rate', '${(metrics['engagement_rate'] ?? 0).toStringAsFixed(2)}%'),
                              ],
                            ),
                          ),
                        ),

                      const SizedBox(height: 16),
                      const Text(
                        'Quick Access',
                        style: TextStyle(
                            fontSize: 16, fontWeight: FontWeight.w600),
                      ),
                      const SizedBox(height: 8),
                      _navCard(
                        context,
                        'AI Recommendations',
                        'Actionable insights from your analytics data.',
                        Icons.psychology_outlined,
                        () => Navigator.push(
                          context,
                          MaterialPageRoute(
                              builder: (_) => const AiRecommendationsScreen()),
                        ),
                      ),
                      _navCard(
                        context,
                        'Reports',
                        'View and download generated analytics reports.',
                        Icons.description_outlined,
                        () => Navigator.push(
                          context,
                          MaterialPageRoute(
                              builder: (_) => const AnalyticsReportsScreen()),
                        ),
                      ),
                      _navCard(
                        context,
                        'Data Collection Status',
                        'Monitor analytics collection jobs.',
                        Icons.sync_outlined,
                        () => Navigator.push(
                          context,
                          MaterialPageRoute(
                              builder: (_) =>
                                  const DataCollectionStatusScreen()),
                        ),
                      ),
                      const SizedBox(height: 16),
                      Card(
                        child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: const [
                              Icon(Icons.info_outline, color: Colors.blue),
                              SizedBox(height: 8),
                              Text(
                                'Analytics are collected strictly from connected official social accounts. '
                                'Trigger collection via the web Analytics Studio or API. '
                                'Data is never fabricated — only verified platform metrics are reported.',
                                style: TextStyle(
                                    color: Colors.grey, fontSize: 13),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
    );
  }

  Widget _infoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(color: Colors.grey)),
          Text(value, style: const TextStyle(fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }

  Widget _navCard(BuildContext context, String title, String subtitle,
      IconData icon, VoidCallback onTap) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: Icon(icon, color: Theme.of(context).colorScheme.primary),
        title: Text(title),
        subtitle: Text(subtitle,
            style: const TextStyle(fontSize: 12, color: Colors.grey)),
        trailing: const Icon(Icons.chevron_right),
        onTap: onTap,
      ),
    );
  }
}
