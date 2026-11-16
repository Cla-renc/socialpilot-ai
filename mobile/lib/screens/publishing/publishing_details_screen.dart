import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../models/publishing_attempt.dart';
import '../../services/publishing_service.dart';

class PublishingDetailsScreen extends StatefulWidget {
  const PublishingDetailsScreen({super.key, required this.attempt, this.service});

  final PublishingAttempt attempt;
  final PublishingService? service;

  @override
  State<PublishingDetailsScreen> createState() =>
      _PublishingDetailsScreenState();
}

class _PublishingDetailsScreenState extends State<PublishingDetailsScreen> {
  late final PublishingService _service;
  bool _retrying = false;

  @override
  void initState() {
    super.initState();
    _service = widget.service ?? PublishingService();
  }

  Future<void> _retry() async {
    setState(() => _retrying = true);
    try {
      await _service.retryAttempt(widget.attempt.id);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Retry queued successfully.')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Retry failed: $e')),
        );
      }
    } finally {
      if (mounted) setState(() => _retrying = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final attempt = widget.attempt;

    return Scaffold(
      appBar: AppBar(title: const Text('Publishing Details')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (attempt.isProcessing)
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: const [
                      CircularProgressIndicator(),
                      SizedBox(width: 16),
                      Text('Processing...'),
                    ],
                  ),
                ),
              ),
            Card(
              child: Column(
                children: [
                  _DetailTile(label: 'ID', value: '${attempt.id}'),
                  _DetailTile(label: 'Platform', value: attempt.platform),
                  _DetailTile(
                    label: 'Status',
                    value: attempt.status,
                    valueColor: attempt.statusColor,
                  ),
                  if (attempt.contentType != null)
                    _DetailTile(
                        label: 'Content Type', value: attempt.contentType!),
                  _DetailTile(
                      label: 'Attempt #',
                      value: '${attempt.attemptNumber}'),
                  _DetailTile(
                      label: 'Retryable',
                      value: attempt.isRetryable ? 'Yes' : 'No'),
                  if (attempt.externalPostId != null)
                    _DetailTile(
                        label: 'External Post ID',
                        value: attempt.externalPostId!),
                  if (attempt.brandId != null)
                    _DetailTile(
                        label: 'Brand ID', value: '${attempt.brandId}'),
                  if (attempt.scheduledPostId != null)
                    _DetailTile(
                        label: 'Scheduled Post ID',
                        value: '${attempt.scheduledPostId}'),
                  if (attempt.socialAccountId != null)
                    _DetailTile(
                        label: 'Social Account ID',
                        value: '${attempt.socialAccountId}'),
                  if (attempt.publishedAt != null)
                    _DetailTile(
                        label: 'Published At',
                        value:
                            attempt.publishedAt!.toLocal().toString()),
                  if (attempt.errorCode != null)
                    _DetailTile(
                        label: 'Error Code',
                        value: attempt.errorCode!,
                        valueColor: const Color(0xFFF44336)),
                  if (attempt.errorMessage != null)
                    _DetailTile(
                        label: 'Error Message',
                        value: attempt.errorMessage!,
                        valueColor: const Color(0xFFF44336)),
                  _DetailTile(
                      label: 'Created At',
                      value: attempt.createdAt.toLocal().toString()),
                  _DetailTile(
                      label: 'Updated At',
                      value: attempt.updatedAt.toLocal().toString()),
                ],
              ),
            ),
            if (attempt.externalPostUrl != null) ...[
              const SizedBox(height: 12),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'External Post URL',
                        style: TextStyle(
                          fontSize: 12,
                          color: Colors.grey,
                        ),
                      ),
                      const SizedBox(height: 4),
                      InkWell(
                        onTap: () async {
                          final url =
                              Uri.tryParse(attempt.externalPostUrl!);
                          if (url != null &&
                              await canLaunchUrl(url)) {
                            await launchUrl(url,
                                mode: LaunchMode.externalApplication);
                          }
                        },
                        child: Text(
                          attempt.externalPostUrl!,
                          style: const TextStyle(
                            color: Color(0xFF7FE7FF),
                            decoration: TextDecoration.underline,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
            if (attempt.isFailed && attempt.isRetryable) ...[
              const SizedBox(height: 16),
              ElevatedButton.icon(
                onPressed: _retrying ? null : _retry,
                icon: _retrying
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.refresh),
                label: const Text('Retry'),
                style: ElevatedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 16),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _DetailTile extends StatelessWidget {
  const _DetailTile({
    required this.label,
    required this.value,
    this.valueColor,
  });

  final String label;
  final String value;
  final Color? valueColor;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      dense: true,
      title: Text(
        label,
        style: const TextStyle(fontSize: 12, color: Colors.grey),
      ),
      subtitle: Text(
        value,
        style: TextStyle(
          fontSize: 14,
          color: valueColor,
          fontWeight: FontWeight.w500,
        ),
      ),
    );
  }
}
